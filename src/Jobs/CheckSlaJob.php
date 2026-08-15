<?php

namespace Nawasara\Aspirations\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Nawasara\Aspirations\Models\Report;

/**
 * Naikkan tingkat eskalasi laporan yang lewat batas waktu (#2).
 *
 * Inilah yang membuat SLA punya gigi. Tanpa job ini, `sla_due_at` hanya angka
 * di basis data yang tidak pernah berakibat apa-apa — dan OPD yang mendiamkan
 * laporan tidak akan pernah terlihat.
 *
 *   tingkat 0 → belum ada eskalasi
 *   tingkat 1 → lewat batas, diteruskan ke Sekda
 *   tingkat 2 → lewat jauh, masuk Dashboard Bunda
 *
 * ⚠️ Job ini TIDAK mengubah status laporan. Eskalasi adalah perhatian, bukan
 * penyelesaian: menaikkannya menjadi "selesai" secara otomatis justru
 * menghapus persoalan yang seharusnya terlihat.
 */
class CheckSlaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    /**
     * Berapa lama setelah tenggat sebuah laporan naik ke tingkat berikutnya.
     *
     * Bertingkat, bukan sekali lompat: laporan yang terlambat satu jam tidak
     * pantas langsung mendarat di meja Bupati, dan bila semuanya langsung ke
     * sana, tidak ada yang benar-benar diperhatikan.
     */
    protected const ESCALATE_AFTER_HOURS = [
        1 => 0,     // segera setelah lewat tenggat
        2 => 48,    // dua hari kemudian, bila masih juga tidak selesai
    ];

    public function handle(): void
    {
        $naik = 0;

        // withoutGlobalScopes: job berjalan tanpa pengguna, dan ScopedToOpd
        // akan menjawab 'privileged' — tetapi ditulis eksplisit supaya
        // maksudnya terbaca, bukan bergantung pada perilaku yang kebetulan
        // benar.
        Report::withoutGlobalScopes()
            ->overdue()
            ->chunkById(200, function ($reports) use (&$naik) {
                foreach ($reports as $report) {
                    $target = $this->targetLevel($report);

                    if ($target > $report->escalation_level) {
                        $report->escalation_level = $target;
                        $report->save();
                        $naik++;
                    }
                }
            });

        if ($naik > 0) {
            Log::info('aspirations: eskalasi SLA', ['naik' => $naik]);
        }
    }

    /**
     * Tingkat yang seharusnya, dihitung dari tenggat yang PALING AWAL terlewat.
     *
     * Dua tenggat diperiksa — tanggapan dan penyelesaian — karena keduanya
     * rentang yang berbeda. OPD yang menanggapi cepat tetapi tidak kunjung
     * menyelesaikan harus tetap tereskalasi, begitu pula sebaliknya.
     */
    protected function targetLevel(Report $report): int
    {
        $lewat = collect([
            $report->isResponseOverdue() ? $report->response_due_at : null,
            $report->isResolutionOverdue() ? $report->sla_due_at : null,
        ])->filter()->min();

        if (! $lewat) {
            return $report->escalation_level;
        }

        // ⚠️ `diffInHours()` di Carbon versi ini mengembalikan selisih
        // BERTANDA: tenggat yang sudah lewat menghasilkan angka NEGATIF,
        // sehingga perbandingan `>= ambang` tidak pernah benar dan tidak ada
        // laporan yang tereskalasi — diam-diam, tanpa galat.
        //
        // Ditulis eksplisit dari `$lewat` ke sekarang supaya arahnya tidak
        // bergantung pada perilaku pustaka yang dapat berubah antarversi.
        $jamTerlambat = $lewat->diffInHours(now(), false);
        $level = $report->escalation_level;

        foreach (self::ESCALATE_AFTER_HOURS as $tingkat => $ambang) {
            if ($jamTerlambat >= $ambang) {
                $level = max($level, $tingkat);
            }
        }

        return $level;
    }
}
