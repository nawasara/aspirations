<?php

namespace Nawasara\Aspirations\Jobs;

use Nawasara\Aspirations\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Nawasara\Aspirations\Models\Report;
use Nawasara\Aspirations\Support\SlaReminderPlan;
use Nawasara\Notification\Facades\Notify;

/**
 * Peringatan tenggat ke petugas OPD, lewat email.
 *
 * Sebelum ini tenggat hanya menaikkan `escalation_level`: angka di basis data
 * yang dibaca atasan SETELAH terlambat. Yang mencegah keterlambatan adalah
 * peringatan SEBELUM tenggat, dan itu yang dikirim di sini.
 *
 * Tiga peristiwa, masing-masing TEPAT sekali per laporan (kolom penandanya di
 * migrasi `add_sla_reminder_marks`):
 *
 *   tanggapan hampir lewat    belum ada tanggapan pertama, sisa waktu masuk jendela
 *   penyelesaian hampir lewat belum diajukan selesai, sisa waktu masuk jendela
 *   sudah lewat               salah satu tenggat terlewati
 *
 * Jendelanya proporsional; lihat SlaReminderPlan.
 *
 * ⚠️ Satu email per petugas per putaran, berisi SEMUA laporan OPD-nya yang
 * relevan, bukan satu email per laporan. Petugas yang menerima sepuluh email
 * dalam sejam berhenti membacanya, dan yang terlewat justru yang mendesak.
 *
 * Penerima: anggota aktif OPD laporan itu.
 *
 *   berizin `aspirations.report.respond`   semua peristiwa
 *   hanya berizin `aspirations.report.verify` hanya "sudah lewat"
 *
 * Laporan tidak punya kolom "petugas penanggung jawab" per orang, jadi seluruh
 * petugas OPD yang dapat menanganinya yang diberi tahu. Pemeriksa (Kabid)
 * adalah "atasan" dalam permintaan aplikasi ("lewat batas waktu → petugas +
 * atasannya"); ia tidak diberi peringatan "hampir lewat" karena bukan ia yang
 * mengerjakan, dan peringatan yang tidak dapat ditindaklanjuti penerimanya
 * hanya mengajari orang mengabaikannya.
 *
 * Kanal: `sla_reminders.channels`, bawaannya email saja. Telegram dapat
 * ditambahkan, tetapi hanya sampai ke pengguna yang punya `telegram_chat_id`;
 * yang tidak punya dilewati NotificationService tanpa galat.
 *
 * ⚠️ Tidak dikirim ke grup Telegram bersama. Grup itu (topik peringatan
 * sistem) dibaca orang di luar OPD, dan judul laporan ditulis warga: isinya
 * dapat memuat nama dan alamat orang.
 *
 * ── Tidak mengirim dua kali ──────────────────────────────────────────────
 *
 * Satu kolom penanda per (laporan, jenis peristiwa) di tabel laporan. Itulah
 * kunci dedup-nya, stabil per laporan dan per tenggat, bukan per putaran.
 * eventsFor() tidak mengembalikan peristiwa yang penandanya sudah terisi, dan
 * mark() mengisinya setelah putaran mengirim. Tidak ada cooldown berbasis
 * waktu: cooldown 0 pernah membanjiri kanal lain di repo ini.
 *

 * Laporan yang DITANDAI (`flagged_at`) dilewati: ia belum diteruskan ke OPD,
 * dan petugas tidak dapat melihatnya di panel.
 */
class SendSlaRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public const KIND_RESPONSE = 'response';

    public const KIND_RESOLUTION = 'resolution';

    public const KIND_OVERDUE = 'overdue';

    /** @param  bool  $dryRun  hitung dan cetak saja, tanpa mengirim dan menandai */
    public function __construct(public bool $dryRun = false) {}

    /** @return array<int, array{opd_id:int, recipients:int, items:int}> ringkasan per OPD */
    public function handle(): array
    {
        $now = now();
        $byOpd = [];
        $limits = SlaReminderPlan::limitsFrom((array) config('nawasara-aspirations.sla_reminders', []));

        Report::withoutGlobalScopes()
            ->with('category')
            ->whereIn('status', Report::OPEN_STATUSES)
            ->whereNotNull('opd_id')
            ->whereNull('flagged_at')
            ->chunkById(200, function ($reports) use ($now, $limits, &$byOpd) {
                foreach ($reports as $report) {
                    foreach ($this->eventsFor($report, $now, $limits) as $kind) {
                        $byOpd[$report->opd_id][] = ['report' => $report, 'kind' => $kind];
                    }
                }
            });

        $summary = [];
        $verifiersOnOverdue = Settings::notifyVerifiersOnOverdue();

        foreach ($byOpd as $opdId => $items) {
            $recipients = $this->recipientsFor((int) $opdId);
            $sent = 0;

            if (! $this->dryRun) {
                foreach ($recipients as ['user' => $user, 'responds' => $responds, 'verifies' => $verifies]) {
                    $mine = self::itemsForRecipient($items, $responds, $verifies && $verifiersOnOverdue);
                    if ($mine !== []) {
                        $this->send($user, $mine, $now);
                        $sent++;
                    }
                }
                $this->mark($items, $now);
            }

            $summary[] = ['opd_id' => (int) $opdId, 'recipients' => $this->dryRun ? $recipients->count() : $sent, 'items' => count($items)];
        }

        if ($summary !== [] && ! $this->dryRun) {
            Log::info('aspirations: peringatan tenggat dikirim', ['per_opd' => $summary]);
        }

        return $summary;
    }

    /**
     * Peristiwa yang belum pernah dikirim untuk laporan ini.
     *
     * "Sudah lewat" diukur terhadap `$now` putaran ini, bukan now() per
     * laporan, supaya seluruh putaran memakai satu jam yang sama dengan
     * peringatan "hampir lewat".
     *
     * @param  array{share: float, min: float, max: float}|null  $limits
     * @return array<int,string>
     */
    public function eventsFor(Report $report, \Carbon\CarbonInterface $now, ?array $limits = null): array
    {
        $events = [];
        $start = $report->received_at;

        if (SlaReminderPlan::shouldWarn($start, $report->response_due_at,
            $report->first_responded_at !== null, $report->response_warned_at !== null, $now, $limits)) {
            $events[] = self::KIND_RESPONSE;
        }

        if (SlaReminderPlan::shouldWarn($start, $report->sla_due_at,
            $report->resolution_submitted_at !== null, $report->resolution_warned_at !== null, $now, $limits)) {
            $events[] = self::KIND_RESOLUTION;
        }

        $responseOverdue = $report->first_responded_at === null
            && $report->response_due_at !== null && $now->greaterThan($report->response_due_at);
        $resolutionOverdue = $report->resolution_submitted_at === null
            && $report->sla_due_at !== null && $now->greaterThan($report->sla_due_at);

        if ($report->overdue_notified_at === null && ($responseOverdue || $resolutionOverdue)) {
            $events[] = self::KIND_OVERDUE;
        }

        return $events;
    }

    /**
     * Bagian peringatan yang menjadi urusan seorang penerima.
     *
     * Petugas penanganan menerima semuanya; pemeriksa yang tidak menangani
     * hanya menerima "sudah lewat". Fungsi murni, supaya pembagiannya dapat
     * diuji tanpa basis data.
     *
     * @param  array<int, array{report: Report, kind: string}>  $items
     * @return array<int, array{report: Report, kind: string}>
     */
    public static function itemsForRecipient(array $items, bool $responds, bool $verifiesOverdue): array
    {
        if ($responds) {
            return $items;
        }

        if ($verifiesOverdue) {
            return array_values(array_filter($items, fn ($i) => $i['kind'] === self::KIND_OVERDUE));
        }

        return [];
    }

    /**
     * Anggota aktif OPD yang berizin menangani atau memeriksa laporan.
     *
     * @return Collection<int, array{user: object, responds: bool, verifies: bool}>
     */
    protected function recipientsFor(int $opdId): Collection
    {
        $ids = DB::table('nawasara_registry_memberships')
            ->where('opd_id', $opdId)
            ->where('aktif', true)
            ->pluck('user_id');

        if ($ids->isEmpty()) {
            return collect();
        }

        $model = config('auth.providers.users.model');

        return $model::query()
            ->whereIn('id', $ids)
            ->get()
            ->filter(fn ($user) => $user->email || ($user->telegram_chat_id ?? null))
            ->map(fn ($user) => [
                'user' => $user,
                'responds' => $user->can('aspirations.report.respond'),
                'verifies' => $user->can('aspirations.report.verify'),
            ])
            ->filter(fn ($r) => $r['responds'] || $r['verifies'])
            ->values();
    }

    /**
     * Kanal dari config. Nilai kosong atau keliru kembali ke email, supaya
     * salah ketik di config tidak mematikan peringatan diam-diam.
     *
     * @return array<int, string>
     */
    public static function channels(mixed $configured): array
    {
        $allowed = ['email', 'telegram'];
        $channels = array_values(array_intersect($allowed, (array) $configured));

        return $channels === [] ? ['email'] : $channels;
    }

    /**
     * Ditandai SETELAH dikirim, dan juga bila OPD-nya tidak punya petugas:
     * tanpa penerima tidak ada yang dapat dikirim, dan memeriksa ulang tiap
     * jam hanya membuat log yang sama berulang. OPD tanpa petugas terlihat di
     * ringkasan log dengan `recipients: 0`.
     *
     * @param  array<int, array{report: Report, kind: string}>  $items
     */
    protected function mark(array $items, \Carbon\CarbonInterface $now): void
    {
        foreach ($items as ['report' => $report, 'kind' => $kind]) {
            $column = match ($kind) {
                self::KIND_RESPONSE => 'response_warned_at',
                self::KIND_RESOLUTION => 'resolution_warned_at',
                self::KIND_OVERDUE => 'overdue_notified_at',
            };
            $report->forceFill([$column => $now])->saveQuietly();
        }
    }

    /** @param  array<int, array{report: Report, kind: string}>  $items */
    protected function send(object $officer, array $items, \Carbon\CarbonInterface $now): void
    {
        $overdue = count(array_filter($items, fn ($i) => $i['kind'] === self::KIND_OVERDUE));
        $subject = $overdue > 0
            ? "{$overdue} laporan warga melewati batas waktu"
            : count($items).' laporan warga mendekati batas waktu';

        try {
            Notify::to($officer)
                ->channel(self::channels(config('nawasara-aspirations.sla_reminders.channels', ['email'])))
                ->subject($subject)
                ->body(self::composeBody($items, $now))
                ->context([
                    'kind' => 'aspirations.sla.reminder',
                    'report_codes' => array_values(array_unique(array_map(fn ($i) => $i['report']->code, $items))),
                ])
                ->send();
        } catch (\Throwable $e) {
            // Satu petugas gagal tidak boleh menghentikan peringatan ke yang lain.
            Log::warning('aspirations: peringatan tenggat gagal', ['user' => $officer->id ?? null, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Badan email, HTML.
     *
     * ⚠️ Judul laporan DITULIS WARGA, dan badan ini dirender tanpa escape oleh
     * layout email. Setiap nilai dari laporan melewati e(); tanpa itu warga
     * dapat menyisipkan HTML, tautan palsu misalnya, ke kotak masuk petugas.
     *
     * @param  array<int, array{report: Report, kind: string}>  $items
     */
    public static function composeBody(array $items, \Carbon\CarbonInterface $now): string
    {
        $groups = [
            self::KIND_OVERDUE => 'Sudah melewati batas waktu',
            self::KIND_RESPONSE => 'Belum ditanggapi, batas tanggapan segera tiba',
            self::KIND_RESOLUTION => 'Batas penyelesaian segera tiba',
        ];

        $html = '<p>Laporan warga berikut perlu segera ditindaklanjuti.</p>';

        foreach ($groups as $kind => $heading) {
            $rows = array_filter($items, fn ($i) => $i['kind'] === $kind);
            if ($rows === []) {
                continue;
            }

            $html .= '<p><strong>'.e($heading).'</strong></p><ul>';
            foreach ($rows as ['report' => $report]) {
                $due = $kind === self::KIND_RESPONSE ? $report->response_due_at : $report->sla_due_at;
                if ($kind === self::KIND_OVERDUE) {
                    $due = $report->isResponseOverdue() ? $report->response_due_at : $report->sla_due_at;
                }

                $when = $due?->copy()->timezone('Asia/Jakarta')->translatedFormat('d M Y H:i').' WIB';
                $note = $kind === self::KIND_OVERDUE
                    ? 'lewat sejak '.$when
                    : 'tenggat '.$when.', sisa '.SlaReminderPlan::humanRemaining($now, $due);

                $url = self::detailUrl($report->code);
                $html .= '<li><a href="'.e($url).'">'.e($report->code).'</a> · '.e($report->title)
                    .($report->category ? ' <em>('.e($report->category->name).')</em>' : '')
                    .'<br><small>'.e($note).'</small></li>';
            }
            $html .= '</ul>';
        }

        return $html.'<p><small>Pesan otomatis dari Nawasara. Satu laporan hanya diperingatkan sekali untuk tiap tenggatnya.</small></p>';
    }

    protected static function detailUrl(string $code): string
    {
        try {
            return route('nawasara-aspirations.reports.detail', $code);
        } catch (\Throwable) {
            return rtrim((string) config('app.url'), '/').'/nawasara-aspirations/reports/'.$code;
        }
    }
}
