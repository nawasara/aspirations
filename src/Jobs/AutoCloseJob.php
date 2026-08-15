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
 * Tutup laporan selesai yang tidak kunjung dinilai warga (#7).
 *
 * Warga tidak wajib menilai. Tanpa job ini, laporan yang sudah beres tetap
 * menunggu penilaian selamanya dan angka "menunggu penilaian" tumbuh tanpa
 * arti.
 *
 * ⚠️ Yang ditutup HANYA laporan yang sudah `resolved` — yaitu yang sudah
 * diverifikasi Kabid. Job ini tidak pernah menyelesaikan pekerjaan yang belum
 * diperiksa siapa pun; itu urusan CheckVerificationDueJob, dan pembedaan ini
 * yang menjaga tahap verifikasi tetap berarti.
 *
 * `rating` dibiarkan NULL, tidak diisi nilai bawaan. Menuliskan angka yang
 * tidak pernah diberikan warga akan mencemari rerata kepuasan — dan rerata
 * itulah yang dibaca pimpinan.
 */
class AutoCloseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function handle(): void
    {
        $hari = (int) config('nawasara-aspirations.rating.auto_close_days', 7);
        $batas = now()->subDays($hari);
        $ditutup = 0;

        Report::withoutGlobalScopes()
            ->where('status', Report::STATUS_RESOLVED)
            ->whereNull('rating')
            ->whereNotNull('verified_at')
            ->where('verified_at', '<', $batas)
            // Penanda "sudah lewat masa penilaian" — dipakai panel untuk
            // membedakan laporan yang menunggu nilai dari yang memang sudah
            // tuntas tanpa nilai.
            ->whereNull('rated_closed_at')
            ->chunkById(200, function ($reports) use (&$ditutup) {
                foreach ($reports as $report) {
                    $report->rated_closed_at = now();
                    $report->save();
                    $ditutup++;
                }
            });

        if ($ditutup > 0) {
            Log::info('aspirations: laporan ditutup tanpa penilaian', ['jumlah' => $ditutup]);
        }
    }
}
