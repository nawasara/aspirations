<?php

namespace Nawasara\Aspirations\Console;

use Illuminate\Console\Command;
use Nawasara\Aspirations\Models\District;
use Nawasara\Aspirations\Models\Report;
use Nawasara\Aspirations\Models\Village;
use Nawasara\Aspirations\Services\BoundaryLocator;

/**
 * Mengisi desa dan kecamatan laporan yang sudah ada dari koordinatnya.
 *
 * Tanpa `--force`: hanya laporan yang belum berkecamatan. Aman dijalankan
 * berkali-kali.
 *
 * Dengan `--force`: menghitung ulang SEMUA, dan mencetak setiap laporan yang
 * wilayahnya berubah. Dipakai sekali setelah pencocokan pindah dari titik
 * tengah kecamatan ke batas wilayah BIG: `district_code` ditulis sekali saat
 * laporan masuk, jadi laporan perbatasan yang terlanjur salah tidak membaik
 * sendiri. Di produksi saat itu 7 dari 30 laporan berpindah kecamatan.
 */
class BackfillDistrictsCommand extends Command
{
    protected $signature = 'aspirations:backfill-districts
                            {--force : Hitung ulang juga yang sudah terisi}';

    protected $description = 'Cocokkan koordinat laporan ke desa dan kecamatan Ponorogo menurut batas wilayah';

    public function handle(BoundaryLocator $locator): int
    {
        if (District::count() === 0 || Village::count() === 0) {
            $this->error('Master wilayah kosong. Jalankan DistrictSeeder dan VillageSeeder lebih dulu.');

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

        $names = District::pluck('name', 'code');
        $cocok = 0;
        $diluar = 0;
        $pindah = [];

        // Diproses per potong: seluruh laporan sekaligus akan menahan memori
        // sebanding jumlah barisnya, dan tabel ini tumbuh terus.
        $query->chunkById(200, function ($reports) use ($locator, $names, &$cocok, &$diluar, &$pindah) {
            foreach ($reports as $report) {
                $before = $report->district_code;
                $found = $locator->applyTo($report);

                $found ? $cocok++ : $diluar++;

                if ($before !== null && $before !== $report->district_code) {
                    $pindah[] = [
                        $report->code,
                        $names[$before] ?? $before,
                        $names[$report->district_code] ?? '(di luar wilayah)',
                        $report->village ?? '',
                    ];
                }

                // Menyimpan tanpa memicu event: laporan lama tidak boleh
                // memicu notifikasi atau perubahan SLA hanya karena
                // wilayahnya dikoreksi.
                $report->saveQuietly();
            }
        });

        $this->info("  diperiksa : {$total}");
        $this->info("  tercocok  : {$cocok}");

        if ($diluar > 0) {
            // Bukan kegagalan: laporan dari luar Ponorogo, atau koordinat
            // yang rusak. Disebutkan supaya angkanya tidak terlihat hilang.
            $this->warn("  di luar wilayah : {$diluar}");
        }

        if ($pindah !== []) {
            $this->newLine();
            $this->warn('  Berpindah kecamatan: '.count($pindah));
            $this->table(['Laporan', 'Sebelumnya', 'Sekarang', 'Desa'], $pindah);
        }

        return self::SUCCESS;
    }
}
