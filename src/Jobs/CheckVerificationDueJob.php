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
 * Kabid diam melewati batas verifikasi (#19).
 *
 * Tanpa job ini, laporan menggantung selamanya di `awaiting_verification`:
 * pekerjaannya sudah selesai, tetapi warga melihat "sedang diproses" tanpa
 * ujung. Itu justru lubang yang dibuka oleh keputusan menambah tahap
 * verifikasi — jadi harus ditutup di tempat yang sama.
 *
 * ⚠️ TIDAK menutup laporan secara otomatis. Auto-resolve akan mengembalikan
 * persis lubang yang ditutup D1: pekerjaan dinyatakan selesai tanpa ada yang
 * benar-benar memeriksanya. Yang dilakukan adalah menaikkan eskalasi, supaya
 * kelambatan itu terlihat oleh atasannya.
 */
class CheckVerificationDueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function handle(): void
    {
        $tereskalasi = 0;

        Report::withoutGlobalScopes()
            ->where('status', Report::STATUS_AWAITING_VERIFICATION)
            ->whereNotNull('verification_due_at')
            ->where('verification_due_at', '<', now())
            ->chunkById(200, function ($reports) use (&$tereskalasi) {
                foreach ($reports as $report) {
                    if ($report->escalation_level >= 1) {
                        continue;
                    }

                    $report->escalation_level = 1;
                    $report->save();
                    $tereskalasi++;
                }
            });

        if ($tereskalasi > 0) {
            Log::info('aspirations: verifikasi melewati batas', ['tereskalasi' => $tereskalasi]);
        }
    }
}
