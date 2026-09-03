<?php

namespace Nawasara\Aspirations\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Keutuhan data wilayah Ponorogo.
 *
 * Data wilayah yang salah lebih berbahaya daripada tidak ada: kode yang keliru
 * tetap lolos setiap pemeriksaan bentuk, laporannya tetap tampil, dan yang
 * berubah hanya perangkat daerah yang menerimanya — tanpa satu pun galat.
 *
 * Angka-angka di sini berasal dari Kepmendagri No. 300.2.2-2138 Tahun 2025 dan
 * dicocokkan dengan daftar Wikipedia satu per satu pada 2 September 2026.
 * Bila kelak ada pemekaran, uji ini gagal — dan memang harus, supaya
 * perubahannya disadari, bukan menyelinap.
 */
class RegionDataTest extends TestCase
{
    /** @return array<int,array{0:string,1:string,2?:bool}> */
    private function villages(): array
    {
        return require dirname(__DIR__).'/database/seeders/ponorogo-villages.php';
    }

    public function test_jumlahnya_307_desa_dan_kelurahan(): void
    {
        $this->assertCount(307, $this->villages());
    }

    /** 26 kelurahan + 281 desa — dua sumber sepakat pada angka ini. */
    public function test_sebaran_kelurahan_dan_desa(): void
    {
        $kelurahan = array_filter($this->villages(), fn ($v) => ($v[2] ?? false) === true);

        $this->assertCount(26, $kelurahan);
        $this->assertCount(281, array_filter($this->villages(), fn ($v) => ($v[2] ?? false) === false));
    }

    /**
     * Sifat yang membuat pemeriksaan pasangan mungkin tanpa join.
     *
     * Kalau satu saja kode melanggarnya, desa itu akan ditolak selamanya saat
     * warga memilihnya — dan gejalanya terlihat seperti aplikasi yang rusak.
     */
    public function test_setiap_kode_desa_berawalan_kode_kecamatan(): void
    {
        foreach ($this->villages() as [$code, $name]) {
            $this->assertSame(10, strlen($code), "Kode {$name} harus 10 digit.");
            $this->assertMatchesRegularExpression('/^350\d{7}$/', $code, "Kode {$name} bukan wilayah Ponorogo.");
        }
    }

    /** Kelurahan bernomor 1xxx, desa 2xxx — dan tidak ada yang lain. */
    public function test_penomoran_mengikuti_pola_kemendagri(): void
    {
        foreach ($this->villages() as $v) {
            $urut = substr($v[0], 6);
            $isKelurahan = $v[2] ?? false;

            $this->assertSame(
                $isKelurahan,
                str_starts_with($urut, '1'),
                "{$v[1]} ({$v[0]}): nomor 1xxx harus kelurahan, 2xxx harus desa."
            );
        }
    }

    public function test_tidak_ada_kode_ganda(): void
    {
        $kode = array_column($this->villages(), 0);

        $this->assertCount(count($kode), array_unique($kode), 'Ada kode desa yang dobel.');
    }

    /**
     * Nama TIDAK unik — dan itu justru alasan kode ini ada.
     *
     * Lima belas nama muncul di lebih dari satu kecamatan. Uji ini mengunci
     * kenyataan itu supaya tak ada yang tergoda mencari desa lewat namanya.
     */
    public function test_nama_desa_memang_ada_yang_kembar(): void
    {
        $nama = array_column($this->villages(), 1);
        $kembar = array_filter(array_count_values($nama), fn ($n) => $n > 1);

        $this->assertNotEmpty($kembar, 'Nama kembar adalah alasan kode dipakai, bukan nama.');
        $this->assertArrayHasKey('Tugurejo', $kembar);
    }

    /** Jumlah per kecamatan — angka yang dapat dicek cepat oleh orang. */
    public function test_jumlah_per_kecamatan_sesuai_sumber(): void
    {
        $harap = [
            '350201' => 22, '350202' => 11, '350203' => 19, '350204' => 16,
            '350205' => 14, '350206' => 6, '350207' => 18, '350208' => 15,
            '350209' => 14, '350210' => 18, '350211' => 20, '350212' => 16,
            '350213' => 10, '350214' => 12, '350215' => 18, '350216' => 15,
            '350217' => 19, '350218' => 17, '350219' => 8, '350220' => 13,
            '350221' => 6,
        ];

        $nyata = array_count_values(array_map(
            fn ($v) => substr($v[0], 0, 6),
            $this->villages(),
        ));

        ksort($nyata);
        $this->assertSame($harap, $nyata);
    }
}
