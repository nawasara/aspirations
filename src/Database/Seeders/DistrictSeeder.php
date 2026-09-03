<?php

namespace Nawasara\Aspirations\Database\Seeders;

use Illuminate\Database\Seeder;
use Nawasara\Aspirations\Models\District;

/**
 * 21 kecamatan Kabupaten Ponorogo — kode KEMENDAGRI.
 *
 * Sumber: Kepmendagri No. 300.2.2-2138 Tahun 2025 (diverifikasi 2 September
 * 2026, cocok dengan daftar Wikipedia sampai jumlah desa per kecamatan).
 *
 * ## ⚠️ Kode ini pernah memakai BPS, dan sengaja DIPINDAH
 *
 * Indonesia punya tiga sistem kode wilayah yang berbeda — Kemendagri, BPS,
 * dan Kemenkeu — dan ketiganya TIDAK sama. Untuk Ponorogo, hanya 10 dari 21
 * kecamatan yang nomor urutnya kebetulan sama:
 *
 *     Kemendagri 350201 Slahung  ←→  BPS 3502020 Slahung
 *     Kemendagri 350202 Ngrayun  ←→  BPS 3502010 Ngrayun   (tertukar)
 *     Kemendagri 350220 Jambon   ←→  BPS 3502130 Jambon    (jauh berbeda)
 *
 * Dipindah ke Kemendagri karena **Dukcapil dan pemerintahan desa memakai
 * Kemendagri**, dan seluruh gunanya kode ini adalah menyalurkan laporan warga
 * ke perangkat daerah yang benar. Kode BPS untuk keperluan statistik, dan
 * daftar desanya pun tidak tersedia untuk diunduh.
 *
 * Dikerjakan saat produksi baru berisi 12 laporan dan 2 profil. Setahun lagi
 * biayanya jauh lebih besar.
 *
 * ## Panjang kode
 *
 * Kemendagri: kecamatan **6 digit** (350202), desa **10 digit** (3502022001).
 * Ini BERBEDA dari BPS yang memakai 7 dan 10. Jangan disamakan.
 *
 * ## Koordinat
 *
 * ⚠️ Perkiraan pusat kecamatan, bukan titik kantor maupun sentroid poligon
 * resmi. Cukup untuk menaruh penanda peta dan mengelompokkan laporan; TIDAK
 * cukup untuk menentukan batas wilayah, dan tidak dimaksudkan untuk itu.
 * Nilainya dibawa apa adanya dari data BPS sebelumnya — yang berubah kodenya,
 * bukan tempatnya.
 */
class DistrictSeeder extends Seeder
{
    public function run(): void
    {
        $districts = [
            ['350201', 'Slahung',      -8.0331,  111.4306],
            ['350202', 'Ngrayun',      -8.1372,  111.4139],
            ['350203', 'Bungkal',      -7.9944,  111.5083],
            ['350204', 'Sambit',       -7.9556,  111.5528],
            ['350205', 'Sawoo',        -7.9861,  111.5972],
            ['350206', 'Sooko',        -7.8722,  111.6194],
            ['350207', 'Pulung',       -7.8944,  111.5639],
            ['350208', 'Mlarak',       -7.9250,  111.5111],
            ['350209', 'Jetis',        -7.9472,  111.4667],
            ['350210', 'Siman',        -7.9083,  111.4861],
            ['350211', 'Balong',       -7.9500,  111.4083],
            ['350212', 'Kauman',       -7.8778,  111.4111],
            ['350213', 'Badegan',      -7.8556,  111.3667],
            ['350214', 'Sampung',      -7.8083,  111.4056],
            ['350215', 'Sukorejo',     -7.8472,  111.4694],
            ['350216', 'Babadan',      -7.8500,  111.5056],
            ['350217', 'Ponorogo',     -7.8681,  111.4694],
            ['350218', 'Jenangan',     -7.8222,  111.5250],
            ['350219', 'Ngebel',       -7.8000,  111.6083],
            ['350220', 'Jambon',       -7.9028,  111.3639],
            ['350221', 'Pudak',        -7.8333,  111.6417],
        ];

        foreach ($districts as [$code, $name, $lat, $lng]) {
            District::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'latitude' => $lat, 'longitude' => $lng],
            );
        }

        $this->command?->info('  Kecamatan: '.count($districts));
    }
}
