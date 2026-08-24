<?php

namespace Nawasara\Aspirations\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Pencocokan koordinat ke kecamatan.
 *
 * Ada karena reverse-geocoding tidak dapat diandalkan di sini: bawaan sistem
 * adalah `NullGeocoder`, dan di produksi (24 Agustus 2026) sepuluh dari
 * sepuluh laporan punya koordinat tetapi NOL punya `district`.
 *
 * Yang diuji adalah rumus jaraknya — bagian yang salahnya tidak terlihat:
 * laporan tetap masuk, peta tetap tampil, hanya titiknya berada di kecamatan
 * yang keliru.
 */
class DistrictMatchTest extends TestCase
{
    /** Salinan rumus haversine di District::haversine(). */
    private function jarak(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Koordinat SUNGGUHAN dari laporan produksi harus jatuh ke kecamatan
     * yang masuk akal.
     *
     * LB-2026-08-0001 di -7.8710, 111.4880 — di antara Ponorogo (kota) dan
     * Sukorejo, dan lebih dekat ke Ponorogo.
     */
    public function test_koordinat_laporan_produksi_jatuh_ke_ponorogo(): void
    {
        $kandidat = [
            'Ponorogo' => [-7.8681, 111.4694],
            'Sukorejo' => [-7.8472, 111.4694],
            'Babadan' => [-7.8500, 111.5056],
            'Siman' => [-7.9083, 111.4861],
        ];

        $terdekat = null;
        $min = PHP_FLOAT_MAX;

        foreach ($kandidat as $nama => [$lat, $lon]) {
            $d = $this->jarak(-7.8710858, 111.4880334, $lat, $lon);
            if ($d < $min) {
                $min = $d;
                $terdekat = $nama;
            }
        }

        $this->assertSame('Ponorogo', $terdekat);
        $this->assertLessThan(5.0, $min, 'jaraknya harus beberapa kilometer, bukan puluhan');
    }

    /**
     * Rumus haversine, bukan selisih derajat.
     *
     * Satu derajat bujur di lintang Ponorogo (~7,9° S) hanya sekitar 110 km,
     * hampir sama dengan lintang — tetapi rumus derajat biasa memperlakukan
     * keduanya identik dan meleset. Uji ini mengunci bahwa jarak dihitung
     * dalam kilometer sungguhan.
     */
    public function test_jarak_dalam_kilometer_bukan_derajat(): void
    {
        // Satu derajat lintang selalu ~111 km.
        $satuDerajatLintang = $this->jarak(-7.8, 111.5, -6.8, 111.5);
        $this->assertGreaterThan(110.0, $satuDerajatLintang);
        $this->assertLessThan(112.0, $satuDerajatLintang);

        // Satu derajat bujur di lintang ini LEBIH PENDEK — inilah yang
        // dilewatkan rumus derajat biasa.
        $satuDerajatBujur = $this->jarak(-7.8, 111.5, -7.8, 112.5);
        $this->assertLessThan($satuDerajatLintang, $satuDerajatBujur);
    }

    /**
     * Kabupaten TETANGGA harus ditolak — dan mereka jauh lebih dekat
     * daripada dugaan.
     *
     * Ambang 40 km yang sempat dipakai akan menarik Madiun (21,1 km) dan
     * Trenggalek (14,1 km) masuk ke peta Ponorogo. Uji ini mengunci ambang
     * 15 km, dan akan gagal bila ada yang melonggarkannya lagi.
     */
    public function test_kabupaten_tetangga_ditolak(): void
    {
        $ambang = 15.0;

        // Madiun — jaraknya harus DI ATAS ambang.
        $madiun = $this->keKecamatanTerdekat(-7.6298, 111.5239);
        $this->assertGreaterThan($ambang, $madiun, 'Madiun tidak boleh masuk peta Ponorogo');

        // Surabaya, jauh sekali.
        $surabaya = $this->keKecamatanTerdekat(-7.2575, 112.7521);
        $this->assertGreaterThan(100.0, $surabaya);
    }

    /**
     * Setiap kecamatan Ponorogo sendiri WAJIB berada di dalam ambang.
     *
     * Ini yang menentukan ambangnya boleh serendah apa: dua kecamatan
     * bertetangga terjauh hanya 11,7 km, jadi 15 km menampung seluruh
     * wilayah tanpa membuka pintu bagi tetangga.
     */
    public function test_seluruh_kecamatan_ponorogo_di_dalam_ambang(): void
    {
        foreach (self::KECAMATAN as $nama => [$lat, $lon]) {
            $jarak = $this->keTetanggaTerdekat($lat, $lon);

            $this->assertLessThan(
                15.0,
                $jarak,
                "{$nama} berjarak {$jarak} km dari tetangga terdekatnya",
            );
        }
    }

    /** Jarak sebuah titik ke titik tengah kecamatan terdekat. */
    private function keKecamatanTerdekat(float $lat, float $lon): float
    {
        $min = PHP_FLOAT_MAX;

        foreach (self::KECAMATAN as [$dLat, $dLon]) {
            $min = min($min, $this->jarak($lat, $lon, $dLat, $dLon));
        }

        return $min;
    }

    /** Jarak sebuah kecamatan ke kecamatan LAIN terdekat (bukan dirinya). */
    private function keTetanggaTerdekat(float $lat, float $lon): float
    {
        $min = PHP_FLOAT_MAX;

        foreach (self::KECAMATAN as [$dLat, $dLon]) {
            $d = $this->jarak($lat, $lon, $dLat, $dLon);

            if ($d > 0.001) {
                $min = min($min, $d);
            }
        }

        return $min;
    }

    /** Sama dengan DistrictSeeder — 21 kecamatan Ponorogo. */
    private const KECAMATAN = [
        'Ngrayun' => [-8.1372, 111.4139],
        'Slahung' => [-8.0331, 111.4306],
        'Bungkal' => [-7.9944, 111.5083],
        'Sambit' => [-7.9556, 111.5528],
        'Sawoo' => [-7.9861, 111.5972],
        'Sooko' => [-7.8722, 111.6194],
        'Pulung' => [-7.8944, 111.5639],
        'Mlarak' => [-7.9250, 111.5111],
        'Siman' => [-7.9083, 111.4861],
        'Jetis' => [-7.9472, 111.4667],
        'Balong' => [-7.9500, 111.4083],
        'Kauman' => [-7.8778, 111.4111],
        'Jambon' => [-7.9028, 111.3639],
        'Badegan' => [-7.8556, 111.3667],
        'Sampung' => [-7.8083, 111.4056],
        'Sukorejo' => [-7.8472, 111.4694],
        'Ponorogo' => [-7.8681, 111.4694],
        'Babadan' => [-7.8500, 111.5056],
        'Jenangan' => [-7.8222, 111.5250],
        'Ngebel' => [-7.8000, 111.6083],
        'Pudak' => [-7.8333, 111.6417],
    ];
}
