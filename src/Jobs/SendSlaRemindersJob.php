<?php

namespace Nawasara\Aspirations\Jobs;

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
 * Penerima: anggota aktif OPD laporan itu yang berizin
 * `aspirations.report.respond`. Laporan tidak punya kolom "petugas
 * penanggung jawab" per orang, jadi seluruh petugas OPD yang dapat
 * menanganinya yang diberi tahu.
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

        Report::withoutGlobalScopes()
            ->with('category')
            ->whereIn('status', Report::OPEN_STATUSES)
            ->whereNotNull('opd_id')
            ->whereNull('flagged_at')
            ->chunkById(200, function ($reports) use ($now, &$byOpd) {
                foreach ($reports as $report) {
                    foreach ($this->eventsFor($report, $now) as $kind) {
                        $byOpd[$report->opd_id][] = ['report' => $report, 'kind' => $kind];
                    }
                }
            });

        $summary = [];

        foreach ($byOpd as $opdId => $items) {
            $officers = $this->officersFor((int) $opdId);

            if (! $this->dryRun) {
                foreach ($officers as $officer) {
                    $this->send($officer, $items, $now);
                }
                $this->mark($items, $now);
            }

            $summary[] = ['opd_id' => (int) $opdId, 'recipients' => $officers->count(), 'items' => count($items)];
        }

        if ($summary !== [] && ! $this->dryRun) {
            Log::info('aspirations: peringatan tenggat dikirim', ['per_opd' => $summary]);
        }

        return $summary;
    }

    /** @return array<int,string> peristiwa yang belum pernah dikirim untuk laporan ini */
    public function eventsFor(Report $report, \Carbon\CarbonInterface $now): array
    {
        $events = [];
        $start = $report->received_at;

        if (SlaReminderPlan::shouldWarn($start, $report->response_due_at,
            $report->first_responded_at !== null, $report->response_warned_at !== null, $now)) {
            $events[] = self::KIND_RESPONSE;
        }

        if (SlaReminderPlan::shouldWarn($start, $report->sla_due_at,
            $report->resolution_submitted_at !== null, $report->resolution_warned_at !== null, $now)) {
            $events[] = self::KIND_RESOLUTION;
        }

        if ($report->overdue_notified_at === null
            && ($report->isResponseOverdue() || $report->isResolutionOverdue())) {
            $events[] = self::KIND_OVERDUE;
        }

        return $events;
    }

    /** Anggota aktif OPD yang berizin menangani laporan. */
    protected function officersFor(int $opdId): Collection
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
            ->filter(fn ($user) => $user->email && $user->can('aspirations.report.respond'))
            ->values();
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
                ->channel(['email'])
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
