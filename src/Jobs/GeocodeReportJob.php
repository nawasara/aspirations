<?php

namespace Nawasara\Aspirations\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Nawasara\Aspirations\Contracts\GeocodingProvider;
use Nawasara\Aspirations\Models\Report;

/**
 * Isi nama wilayah dari koordinat laporan.
 *
 * Dijalankan sebagai JOB, bukan saat laporan disimpan. Alasannya bukan
 * kerapian: Google bisa lambat atau mati, dan laporan warga tidak boleh gagal
 * terkirim karena layanan pihak ketiga sedang bermasalah. Warga sudah berdiri
 * di lokasi dengan sinyal seadanya — kegagalan di titik itu berarti mereka
 * mengulang dari awal.
 *
 * ⚠️ Wilayah adalah PELENGKAP. Laporan tanpa nama desa tetap sah, tetap
 * didisposisi, tetap ditangani. Yang hilang hanya kemampuan menyaring per
 * wilayah di panel.
 */
class GeocodeReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    /**
     * Dicoba tiga kali, lalu menyerah.
     *
     * Bukan tanpa batas: bila Google menolak karena kunci salah atau kuota
     * habis, mengulang ribuan kali hanya menumpuk antrean dan menambah tagihan
     * tanpa pernah berhasil.
     */
    public int $tries = 3;

    /** Jeda antar-percobaan (detik) — memberi waktu bila gangguan sesaat. */
    public array $backoff = [60, 300];

    public function __construct(
        public string $reportId,
    ) {}

    public function handle(GeocodingProvider $geocoder): void
    {
        $report = Report::withoutGlobalScopes()->find($this->reportId);

        if (! $report) {
            // Laporan terhapus sebelum job jalan — bukan galat.
            return;
        }

        if ($report->latitude === null || $report->longitude === null) {
            return;
        }

        // Sudah pernah terisi? Jangan panggil ulang — tiap panggilan berbiaya.
        if ($report->village !== null || $report->full_address !== null) {
            return;
        }

        $result = $geocoder->reverse((float) $report->latitude, (float) $report->longitude);

        if ($result === null) {
            // Penyedia mengembalikan null untuk KEDUA hal: gagal, dan titik
            // yang memang tidak punya alamat. Keduanya diperlakukan sama —
            // tidak ada yang bisa diisi, dan laporan tetap berjalan.
            Log::info('aspirations: geocoding tidak menghasilkan alamat', [
                'report' => $report->code,
            ]);

            return;
        }

        $report->forceFill([
            'full_address' => $result['full_address'] ?? null,
            'village' => $result['village'] ?? null,
            'district' => $result['district'] ?? null,
        ])->save();

        // `village_id` sengaja TIDAK diisi di sini. Master wilayah belum ada
        // di registry; mencocokkan nama desa ke id membutuhkan tabel itu.
        // Sampai tersedia, `village` (teks) sudah cukup untuk menyaring di
        // panel — dan mengisi id dengan tebakan lebih buruk daripada
        // membiarkannya kosong.
    }
}
