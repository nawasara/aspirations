<?php

namespace Nawasara\Aspirations\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Nawasara\Aspirations\Exceptions\WorkflowException;
use Nawasara\Aspirations\Models\Report;
use Nawasara\Aspirations\Models\Response;
use Nawasara\Aspirations\Support\Settings;
use Nawasara\Registry\Support\MembershipResolver;

/**
 * Mesin status laporan — SATU-SATUNYA jalan mengubah `status`.
 *
 * Jangan memakai `$report->update(['status' => …])`. Setiap perpindahan di
 * sini menegakkan aturan otorisasi DAN meninggalkan barisnya sendiri di
 * `responses`, yang menjadi linimasa warga sekaligus jejak audit. Menyetel
 * kolom langsung melewati keduanya tanpa memberi tanda apa pun.
 *
 * ── Alur (enam status) ───────────────────────────────────────────────────
 *
 *   submitted ──► dispatched ──► in_progress ──► awaiting_verification
 *      │              │                                  │
 *      │              │                                  ├──► resolved
 *      └──────────────┴──► rejected                      └──► in_progress
 *                                                            (Kabid menolak)
 *
 * `scheduled` sengaja tidak ada: pekerjaan yang menunggu anggaran dinyatakan
 * lewat tanggapan lalu laporan ditutup. Status menggantung membuat laporan
 * tidak pernah tuntas dan tidak masuk hitungan siapa pun.
 *
 * ── Yang ditegakkan di sini ──────────────────────────────────────────────
 *
 *   #3  `resolved` wajib disertai foto bukti
 *   #4  tanggapan wajib diisi saat mengubah status
 *   #16 verifikator WAJIB berbeda dari pengerja
 *   #17 hanya pemegang izin verify yang boleh menutup
 *   #24 `verifier_id` wajib diisi saat menyerahkan
 *   #25 verifikator se-OPD — hanya bila KEDUANYA punya keanggotaan
 *   #26 hanya pengguna aktif yang boleh ditunjuk
 */
class ReportWorkflow
{
    /** Perpindahan yang diizinkan. Selain ini ditolak. */
    protected const ALLOWED = [
        Report::STATUS_SUBMITTED => [
            Report::STATUS_DISPATCHED,
            Report::STATUS_REJECTED,
        ],
        Report::STATUS_DISPATCHED => [
            Report::STATUS_IN_PROGRESS,
            Report::STATUS_REJECTED,
        ],
        Report::STATUS_IN_PROGRESS => [
            Report::STATUS_AWAITING_VERIFICATION,
        ],
        Report::STATUS_AWAITING_VERIFICATION => [
            Report::STATUS_RESOLVED,
            // Kabid menolak hasil kerja — dikembalikan, bukan ditutup.
            Report::STATUS_IN_PROGRESS,
        ],
        // Laporan yang dibuka kembali karena penilaian rendah (#6) kembali ke
        // in_progress lewat reopen(), bukan lewat transition().
        Report::STATUS_RESOLVED => [],
        Report::STATUS_REJECTED => [],
    ];

    public function __construct(
        protected MembershipResolver $memberships,
    ) {}

    /**
     * Staf OPD mulai mengerjakan.
     *
     * `first_responded_at` diisi DI SINI, dan hanya sekali. Yang menentukan
     * adalah tanggapan tertulis yang terlihat warga — bukan sekadar membuka
     * laporan atau menggeser status. Kalau perubahan status yang dihitung, OPD
     * bisa "memenuhi SLA" tanpa sepatah kata pun kepada warga: angkanya
     * tercapai, maksudnya lewat.
     */
    public function startWork(Report $report, Authenticatable $actor, string $body): Report
    {
        return $this->transition($report, Report::STATUS_IN_PROGRESS, $actor, $body, function (Report $r) use ($actor) {
            $r->responded_by = $actor->getAuthIdentifier();

            if ($r->first_responded_at === null) {
                $r->first_responded_at = now();
            }
        });
    }

    /**
     * Staf menyerahkan pekerjaan ke Kabid pilihannya (D1b).
     *
     * Kabid DIPILIH petugas, bukan ditentukan sistem — petugas yang bekerja di
     * dinasnya tahu siapa atasannya, jauh lebih tahu daripada tabel mana pun
     * yang bisa kita isi hari ini.
     */
    public function submitForVerification(
        Report $report,
        Authenticatable $actor,
        Authenticatable $verifier,
        string $body,
    ): Report {
        $this->assertVerifierUsable($report, $actor, $verifier);

        return $this->transition(
            $report,
            Report::STATUS_AWAITING_VERIFICATION,
            $actor,
            $body,
            function (Report $r) use ($actor, $verifier) {
                $r->responded_by = $r->responded_by ?: $actor->getAuthIdentifier();
                $r->verifier_id = $verifier->getAuthIdentifier();

                // Solution time aktual berhenti DI SINI, bukan saat Kabid
                // menyetujui. Kalau diukur sampai verified_at, OPD yang bekerja
                // cepat tercatat lambat gara-gara atasannya menunda.
                $r->resolution_submitted_at = now();

                $jam = Settings::verificationHours();
                $r->verification_due_at = now()->addHours($jam);
            }
        );
    }

    /**
     * Kabid menyetujui — laporan selesai.
     *
     * ⚠️ #16 ditegakkan di sini dan tidak boleh punya pengecualian: ini
     * satu-satunya penjaga struktural yang tersisa setelah #25 dilonggarkan.
     */
    public function approve(Report $report, Authenticatable $actor): Report
    {
        $actorId = $actor->getAuthIdentifier();

        // Pemeriksaannya sendiri ada di assertMayResolve(), yang dipanggil
        // transition() untuk SETIAP perpindahan ke `resolved` — termasuk yang
        // tidak lewat metode ini.

        return $this->transition(
            $report,
            Report::STATUS_RESOLVED,
            $actor,
            null,
            function (Report $r) use ($actorId) {
                $r->verified_by = $actorId;
                $r->verified_at = now();
            }
        );
    }

    /**
     * Kabid menolak hasil kerja — dikembalikan ke staf, bukan ditutup.
     *
     * Alasan WAJIB diisi: tanpa itu staf tidak tahu apa yang harus diperbaiki,
     * dan penolakan berubah menjadi hambatan tanpa arah.
     */
    public function rejectWork(Report $report, Authenticatable $actor, string $reason): Report
    {
        if (! $this->can($actor, 'aspirations.report.verify')) {
            throw new WorkflowException('Anda tidak berwenang memverifikasi laporan.');
        }

        if (trim($reason) === '') {
            throw new WorkflowException('Alasan pengembalian wajib diisi.');
        }

        return $this->transition(
            $report,
            Report::STATUS_IN_PROGRESS,
            $actor,
            $reason,
            function (Report $r) {
                // Batas verifikasi dibersihkan; jamnya baru berjalan lagi saat
                // pekerjaan diserahkan ulang.
                $r->verification_due_at = null;
                $r->resolution_submitted_at = null;
            }
        );
    }

    /**
     * Perpindahan status umum.
     *
     * Semua jalur di atas bermuara ke sini supaya pemeriksaan dan pencatatan
     * riwayat hanya ada satu tempat.
     */
    public function transition(
        Report $report,
        string $to,
        Authenticatable $actor,
        ?string $body = null,
        ?callable $mutate = null,
    ): Report {
        $from = $report->status;

        if (! in_array($to, self::ALLOWED[$from] ?? [], true)) {
            throw new WorkflowException(
                "Laporan berstatus '{$from}' tidak dapat dipindahkan ke '{$to}'."
            );
        }

        // #16 & #3 & #17 ditegakkan DI SINI, bukan hanya di approve().
        //
        // `transition()` publik supaya panel dapat memakainya untuk perpindahan
        // biasa — dan itu berarti ia dapat dipanggil langsung untuk menutup
        // laporan, melewati approve() beserta seluruh pemeriksaannya. Lubang
        // itu nyata: diuji, dan pengerja berhasil menutup laporannya sendiri.
        //
        // Karena `resolved` adalah satu-satunya status yang menyatakan
        // pekerjaan sah selesai, pemeriksaannya harus melekat pada
        // PERPINDAHANNYA, bukan pada metode yang kebetulan dipakai.
        if ($to === Report::STATUS_RESOLVED) {
            $this->assertMayResolve($report, $actor);
        }

        // #4 — tanggapan wajib. Dikecualikan untuk `resolved`, karena
        // persetujuan Kabid sudah punya jejaknya sendiri (verified_by) dan
        // memaksa kalimat di sana hanya menghasilkan "ok" berulang-ulang.
        if ($to !== Report::STATUS_RESOLVED && trim((string) $body) === '') {
            throw new WorkflowException('Tanggapan wajib diisi saat mengubah status laporan.');
        }

        return DB::transaction(function () use ($report, $from, $to, $actor, $body, $mutate) {
            $report->status = $to;

            if ($mutate) {
                $mutate($report);
            }

            $report->save();

            Response::create([
                'report_id' => $report->id,
                'user_id' => $actor->getAuthIdentifier(),
                'status_from' => $from,
                'status_to' => $to,
                'body' => $body,
                'is_internal' => false,
            ]);

            return $report->refresh();
        });
    }

    /**
     * Bolehkah $actor menutup laporan ini?
     *
     * Dipanggil dari transition(), bukan dari approve() saja — lihat catatan di
     * sana. Menempelkannya pada perpindahan membuatnya mustahil dilewati
     * dengan memilih metode lain.
     */
    protected function assertMayResolve(Report $report, Authenticatable $actor): void
    {
        // #16 — mutlak, tanpa pengecualian. Setelah #25 dilonggarkan, inilah
        // satu-satunya penjaga struktural yang tersisa.
        if ((int) $report->responded_by === (int) $actor->getAuthIdentifier()) {
            throw new WorkflowException(
                'Anda tidak dapat memverifikasi pekerjaan Anda sendiri. Verifikasi harus dilakukan orang lain.'
            );
        }

        // #17 — hanya pemegang izin verify.
        if (! $this->can($actor, 'aspirations.report.verify')) {
            throw new WorkflowException('Anda tidak berwenang memverifikasi laporan.');
        }

        // #3 — foto bukti wajib, TETAPI hanya untuk kategori yang memang
        // menghasilkan pekerjaan fisik.
        //
        // Aspirasi warga dijawab dengan tanggapan, bukan dengan foto; laporan
        // pungli berujung pemeriksaan Inspektorat, bukan sesuatu yang dapat
        // dipotret. Memaksa foto di situ hanya mendorong petugas memotret apa
        // saja agar tombol "selesai" dapat ditekan — dan foto asal-asalan
        // membuat SELURUH bukti kehilangan arti, termasuk yang sungguhan.
        if ($report->category?->requires_evidence
            && ! $report->attachments()->where('kind', 'evidence')->exists()) {
            throw new WorkflowException(
                'Laporan tidak dapat diselesaikan tanpa foto bukti penanganan.'
            );
        }
    }

    /**
     * Bolehkah $verifier ditunjuk sebagai tujuan verifikasi?
     *
     * Dipisah menjadi metode publik supaya panel dapat menyaring daftar
     * pilihan memakai aturan yang sama persis dengan yang ditegakkan saat
     * menyimpan — bukan dua salinan aturan yang lambat laun berbeda.
     */
    public function assertVerifierUsable(
        Report $report,
        Authenticatable $actor,
        Authenticatable $verifier,
    ): void {
        $actorId = (int) $actor->getAuthIdentifier();
        $verifierId = (int) $verifier->getAuthIdentifier();

        // #24 — harus ada tujuannya.
        if ($verifierId === 0) {
            throw new WorkflowException('Kabid tujuan verifikasi wajib dipilih.');
        }

        // #16 — tidak boleh diri sendiri. Mutlak, tanpa pengecualian.
        if ($actorId === $verifierId) {
            throw new WorkflowException(
                'Anda tidak dapat menunjuk diri sendiri sebagai pemeriksa.'
            );
        }

        // #26 — hanya pengguna aktif, supaya laporan tidak menggantung pada
        // orang yang sudah pindah atau pensiun.
        if (property_exists($verifier, 'is_active') && $verifier->is_active === false) {
            throw new WorkflowException('Pengguna yang dipilih sudah tidak aktif.');
        }

        // #25 — se-OPD, TETAPI hanya bila keduanya punya keanggotaan.
        // Saat rencana ini ditulis, 44% pengguna belum tertaut OPD mana pun;
        // aturan yang kaku akan menolak hampir separuh penyerahan pada hari
        // pertama. Aturan ini mengetat dengan sendirinya seiring data membaik,
        // tanpa perlu mengubah kode.
        $opdActor = $this->memberships->opdIdFor($actor);
        $opdVerifier = $this->memberships->opdIdFor($verifier);

        if ($opdActor !== null && $opdVerifier !== null && $opdActor !== $opdVerifier) {
            throw new WorkflowException(
                'Pemeriksa harus berasal dari perangkat daerah yang sama.'
            );
        }
    }

    protected function can(Authenticatable $user, string $permission): bool
    {
        return method_exists($user, 'can') ? $user->can($permission) : false;
    }
}
