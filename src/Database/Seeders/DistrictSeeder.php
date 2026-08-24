<?php

namespace Nawasara\Aspirations\Database\Seeders;

use Illuminate\Database\Seeder;
use Nawasara\Aspirations\Models\District;

/**
 * 21 kecamatan Kabupaten Ponorogo.
 *
 * Kode mengikuti BPS (3502xxx) supaya dapat disandingkan dengan data
 * pemerintah lain tanpa penerjemahan. Koordinatnya titik tengah kecamatan —
 * dipakai menaruh penanda peta dan mencocokkan laporan ke wilayahnya.
 *
 * ⚠️ Koordinat di sini adalah **perkiraan pusat kecamatan**, bukan titik
 * kantor kecamatan maupun sentroid poligon resmi. Ketelitiannya cukup untuk
 * menempatkan penanda dan mengelompokkan laporan; ia TIDAK cukup untuk
 * menentukan batas wilayah, dan tidak dimaksudkan untuk itu.
 */
class DistrictSeeder extends Seeder
{
    public function run(): void
    {
        $districts = [
            ['3502010', 'Ngrayun',      -8.1372, 111.4139],
            ['3502020', 'Slahung',      -8.0331, 111.4306],
            ['3502030', 'Bungkal',      -7.9944, 111.5083],
            ['3502040', 'Sambit',       -7.9556, 111.5528],
            ['3502050', 'Sawoo',        -7.9861, 111.5972],
            ['3502060', 'Sooko',        -7.8722, 111.6194],
            ['3502070', 'Pulung',       -7.8944, 111.5639],
            ['3502080', 'Mlarak',       -7.9250, 111.5111],
            ['3502090', 'Siman',        -7.9083, 111.4861],
            ['3502100', 'Jetis',        -7.9472, 111.4667],
            ['3502110', 'Balong',       -7.9500, 111.4083],
            ['3502120', 'Kauman',       -7.8778, 111.4111],
            ['3502130', 'Jambon',       -7.9028, 111.3639],
            ['3502140', 'Badegan',      -7.8556, 111.3667],
            ['3502150', 'Sampung',      -7.8083, 111.4056],
            ['3502160', 'Sukorejo',     -7.8472, 111.4694],
            ['3502170', 'Ponorogo',     -7.8681, 111.4694],
            ['3502180', 'Babadan',      -7.8500, 111.5056],
            ['3502190', 'Jenangan',     -7.8222, 111.5250],
            ['3502200', 'Ngebel',       -7.8000, 111.6083],
            ['3502210', 'Pudak',        -7.8333, 111.6417],
        ];

        foreach ($districts as [$code, $name, $lat, $lon]) {
            // updateOrCreate, bukan insert: seeder ini dijalankan setiap
            // deploy, dan koordinatnya boleh diperbaiki tanpa menghapus
            // baris yang sudah dirujuk laporan.
            District::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'latitude' => $lat, 'longitude' => $lon],
            );
        }

        $this->command?->info('  Kecamatan: '.District::count());
    }
}
