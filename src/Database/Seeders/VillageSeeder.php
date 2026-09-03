<?php

namespace Nawasara\Aspirations\Database\Seeders;

use Illuminate\Database\Seeder;
use Nawasara\Aspirations\Models\District;
use Nawasara\Aspirations\Models\Village;

/**
 * 307 desa/kelurahan Kabupaten Ponorogo.
 *
 * Datanya di `database/seeders/ponorogo-villages.php` — dipisah supaya
 * perubahan data terbaca sebagai perubahan data di git, bukan bercampur
 * dengan perubahan logika.
 *
 * Aman dijalankan berkali-kali: memakai `updateOrCreate` menurut kode, jadi
 * pemekaran wilayah cukup ditambahkan ke berkas datanya lalu di-seed ulang.
 *
 * ⚠️ Menolak menanam bila kecamatannya belum ada. Desa tanpa kecamatan
 * induknya lolos semua pemeriksaan bentuk tetapi menunjuk wilayah yang tidak
 * ada — persis kegagalan diam yang paling mahal, karena semuanya tampak
 * berjalan sampai ada laporan yang tersalur ke tempat yang salah.
 */
class VillageSeeder extends Seeder
{
    public function run(): void
    {
        $rows = require dirname(__DIR__, 3).'/database/seeders/ponorogo-villages.php';

        $districts = District::pluck('code')->flip();

        if ($districts->isEmpty()) {
            $this->command?->error('  Kecamatan belum ada — jalankan DistrictSeeder lebih dulu.');

            return;
        }

        $dibuat = 0;
        $yatim = [];

        foreach ($rows as $row) {
            $code = $row[0];
            $name = $row[1];
            $isKelurahan = $row[2] ?? false;

            $districtCode = substr($code, 0, 6);

            if (! $districts->has($districtCode)) {
                $yatim[] = "{$code} {$name}";

                continue;
            }

            Village::updateOrCreate(
                ['code' => $code],
                ['district_code' => $districtCode, 'name' => $name, 'is_kelurahan' => $isKelurahan],
            );

            $dibuat++;
        }

        $this->command?->info("  Desa/kelurahan: {$dibuat}");

        if ($yatim !== []) {
            $this->command?->warn('  '.count($yatim).' baris dilewati — kecamatannya tidak dikenali:');
            foreach (array_slice($yatim, 0, 5) as $y) {
                $this->command?->line("    {$y}");
            }
        }
    }
}
