<?php

namespace Nawasara\Aspirations\Console;

use Illuminate\Console\Command;
use Nawasara\Aspirations\Models\District;
use Nawasara\Aspirations\Models\Report;

/**
 * Mengisi `district_code` pada laporan yang sudah ada.
 *
 * Dibutuhkan sekali setelah kolomnya ditambahkan: laporan yang masuk sebelum
 * itu tidak berkecamatan, dan tanpa ini seluruhnya tidak akan pernah muncul
 * di peta meski koordinatnya lengkap.
 *
 * Aman dijalankan berkali-kali — hanya menyentuh baris yang belum terisi.
 */
class BackfillDistrictsCommand extends Command
{
    protected $signature = 'aspirations:backfill-districts
                            {--force : Hitung ulang juga yang sudah terisi}';

    protected $description = 'Cocokkan koordinat laporan ke kecamatan Ponorogo';

    public function handle(): int
    {
        if (District::count() === 0) {
            $this->error('Master kecamatan kosong. Jalankan DistrictSeeder lebih dulu.');

            return self::FAILURE;
        }

        $query = Report::withoutGlobalScopes()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if (! $this->option('force')) {
            $query->whereNull('district_code');
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('Tidak ada laporan yang perlu dicocokkan.');

            return self::SUCCESS;
        }

        $cocok = 0;
        $diluar = 0;

        // Diproses per potong: seluruh laporan sekaligus akan menahan memori
        // sebanding jumlah barisnya, dan tabel ini tumbuh terus.
        $query->chunkById(200, function ($reports) use (&$cocok, &$diluar) {
            foreach ($reports as $report) {
                $district = District::nearest(
                    (float) $report->latitude,
                    (float) $report->longitude,
                );

                if ($district === null) {
                    $diluar++;

                    continue;
                }

                // Menyimpan tanpa memicu event: laporan lama tidak boleh
                // memicu notifikasi atau perubahan SLA hanya karena
                // kecamatannya baru diisi.
                $report->updateQuietly(['district_code' => $district->code]);
                $cocok++;
            }
        });

        $this->info("  diperiksa : {$total}");
        $this->info("  tercocok  : {$cocok}");

        if ($diluar > 0) {
            // Bukan kegagalan — laporan dari luar Ponorogo, atau koordinat
            // yang rusak. Disebutkan supaya angkanya tidak terlihat hilang.
            $this->warn("  di luar wilayah : {$diluar}");
        }

        return self::SUCCESS;
    }
}
