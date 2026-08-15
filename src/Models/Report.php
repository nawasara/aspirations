<?php

namespace Nawasara\Aspirations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nawasara\Registry\Concerns\ScopedToOpd;

/**
 * Laporan warga — model inti Lapor Bunda.
 *
 * Perpindahan status TIDAK dilakukan lewat `$report->update(['status' => …])`.
 * Pakai ReportWorkflow, yang menegakkan aturan #16/#17/#24-26 sekaligus
 * mencatat riwayatnya. Menyetel kolom langsung akan melewati semuanya tanpa
 * memberi tanda apa pun.
 */
class Report extends Model
{
    /**
     * Isolasi per-OPD (#14) — Admin OPD hanya melihat laporan miliknya.
     *
     * Ditegakkan sebagai global scope, bukan where-clause di tiap query:
     * satu klausa yang terlupa di satu tempat sudah cukup membocorkan laporan
     * OPD lain, dan yang terlupa tidak memberi tanda apa pun.
     *
     * ⚠️ TIDAK berpengaruh pada jalur warga. Warga bukan baris `users`, jadi
     * `auth()->user()` null di sana dan resolver menjawab 'privileged' — tanpa
     * filter. Penyaringan milik-sendiri untuk warga dilakukan lewat
     * `where('keycloak_sub')` di controller, dan itu memang tempat yang benar:
     * kepemilikan warga bukan urusan OPD.
     */
    use ScopedToOpd;

    protected $table = 'nawasara_aspirations_reports';

    protected $guarded = [];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'location_accuracy' => 'decimal:2',
        'created_at_device' => 'datetime',
        'received_at' => 'datetime',
        'response_due_at' => 'datetime',
        'first_responded_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'resolution_submitted_at' => 'datetime',
        'verified_at' => 'datetime',
        'verification_due_at' => 'datetime',
        'promised_sla_hours' => 'integer',
        'escalation_level' => 'integer',
        'rating' => 'integer',
        'support_count' => 'integer',
    ];

    // ── Status: ENAM, final per keputusan 13 Agustus 2026 ──
    // `reviewing` dihapus — tidak ada lagi verifikasi Admin Kabupaten di depan.
    // `scheduled` dihapus — pekerjaan menunggu anggaran dijawab lewat tanggapan
    //   lalu laporan ditutup; status menggantung membuat laporan tak pernah
    //   tuntas dan tak masuk hitungan siapa pun.
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_DISPATCHED = 'dispatched';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_AWAITING_VERIFICATION = 'awaiting_verification';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_REJECTED = 'rejected';

    /** Status yang dianggap masih berjalan — dipakai menghitung kepatuhan. */
    public const OPEN_STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_DISPATCHED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_AWAITING_VERIFICATION,
    ];

    /**
     * Peran yang boleh melihat lintas-OPD.
     *
     * Inspektorat masuk karena laporan sensitif (pungli, kelalaian aparatur)
     * memang harus dapat diperiksa lintas dinas — itu tugasnya. Pengawas SLA
     * masuk karena rekap kepatuhan mustahil dibuat dari satu OPD saja.
     */
    protected static function privilegedRoles(): array
    {
        return ['developer', 'inspektorat', 'pengawas-sla', 'admin-kabupaten'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(\Nawasara\Registry\Models\Opd::class, 'opd_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Response::class, 'report_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'report_id');
    }

    public function supports(): HasMany
    {
        return $this->hasMany(Support::class, 'report_id');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'responded_by');
    }

    /** Kabid yang DITUNJUK petugas saat menyerahkan (D1b). */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'verifier_id');
    }

    /** Kabid yang BENAR-BENAR menyetujui — bisa berbeda dari verifier(). */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'verified_by');
    }

    // ── Status ──

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isClosed(): bool
    {
        return ! $this->isOpen();
    }

    // ── SLA ──

    /**
     * Lewat batas TANGGAPAN pertama?
     *
     * Terpisah dari batas selesai dengan sengaja: OPD yang diam 13 hari lalu
     * bekerja di hari ke-14 akan tercatat patuh sempurna bila hanya ada satu
     * SLA. Itu bukan yang ingin diukur.
     */
    public function isResponseOverdue(): bool
    {
        if ($this->first_responded_at !== null || $this->response_due_at === null) {
            return false;
        }

        return now()->greaterThan($this->response_due_at);
    }

    /**
     * Lewat batas SELESAI?
     *
     * Diukur sampai `resolution_submitted_at`, BUKAN `verified_at` — yang
     * kedua sudah termasuk lama Kabid memeriksa, sehingga OPD yang bekerja
     * cepat akan tercatat lambat gara-gara atasannya menunda.
     */
    public function isResolutionOverdue(): bool
    {
        if ($this->resolution_submitted_at !== null || $this->sla_due_at === null) {
            return false;
        }

        return now()->greaterThan($this->sla_due_at);
    }

    /** Kabid diam melewati batas? Laporan tidak boleh menggantung (#19). */
    public function isVerificationOverdue(): bool
    {
        if ($this->status !== self::STATUS_AWAITING_VERIFICATION
            || $this->verification_due_at === null) {
            return false;
        }

        return now()->greaterThan($this->verification_due_at);
    }

    /** Response time aktual, dalam jam. Null bila belum ditanggapi. */
    public function responseHours(): ?float
    {
        if ($this->first_responded_at === null) {
            return null;
        }

        return round($this->received_at->floatDiffInHours($this->first_responded_at), 2);
    }

    /** Solution time aktual, dalam jam. Null bila belum diserahkan. */
    public function solutionHours(): ?float
    {
        if ($this->resolution_submitted_at === null) {
            return null;
        }

        return round($this->received_at->floatDiffInHours($this->resolution_submitted_at), 2);
    }

    // ── Scope ──

    /** Laporan milik satu OPD — dasar antrean Admin OPD (#14). */
    public function scopeForOpd($query, int $opdId)
    {
        return $query->where('opd_id', $opdId);
    }

    /** Antrean verifikasi seorang Kabid — hanya yang ditujukan kepadanya. */
    public function scopeAwaitingVerificationBy($query, int $userId)
    {
        return $query->where('status', self::STATUS_AWAITING_VERIFICATION)
            ->where('verifier_id', $userId);
    }

    /**
     * Laporan yang melewati batas mana pun DAN masih berjalan.
     *
     * ⚠️ Kepatuhan tidak boleh dihitung hanya dari laporan `resolved` — OPD
     * yang mendiamkan laporan justru akan terlihat patuh karena yang terlambat
     * tidak pernah masuk penyebut (#28).
     */
    public function scopeOverdue($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES)
            ->where(function ($q) {
                $q->where(fn ($x) => $x->whereNull('first_responded_at')
                    ->whereNotNull('response_due_at')
                    ->where('response_due_at', '<', now()))
                  ->orWhere(fn ($x) => $x->whereNull('resolution_submitted_at')
                    ->whereNotNull('sla_due_at')
                    ->where('sla_due_at', '<', now()));
            });
    }
}
