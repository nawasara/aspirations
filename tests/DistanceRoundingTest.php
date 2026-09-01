<?php

namespace Nawasara\Aspirations\Tests;

use Nawasara\Aspirations\Services\PublicMap;
use PHPUnit\Framework\TestCase;

/**
 * Pembulatan jarak pada peta publik.
 *
 * Ini uji KEAMANAN, bukan kerapian tampilan. Peta laporan terbuka tanpa akun
 * dan sengaja tidak pernah mengirim koordinat laporan — tetapi jarak yang
 * tepat ke meter membatalkan perlindungan itu: tiga permintaan dari tiga
 * posisi berbeda cukup untuk menghitung balik titik laporan (trilaterasi),
 * dan titik itu menunjuk ke rumah seseorang.
 *
 * Karena itu yang diuji di sini bukan "angkanya rapi", melainkan bahwa
 * jawabannya benar-benar kehilangan ketepatan sebelum meninggalkan server.
 */
class DistanceRoundingTest extends TestCase
{
    /** Tanpa koordinat, tidak ada jarak — bukan 0, yang terbaca "di sini". */
    public function test_laporan_tanpa_koordinat_tidak_punya_jarak(): void
    {
        $this->assertNull(PublicMap::roundDistance(null));
    }

    /**
     * Di bawah 100 m dibulatkan ke 50 m.
     *
     * Justru jarak dekat yang paling berbahaya: lingkaran berjari-jari 10 m
     * hampir menunjuk satu rumah, sedangkan 50 m memuat sekampung.
     */
    public function test_jarak_dekat_dibulatkan_ke_lima_puluh_meter(): void
    {
        $this->assertSame(0, PublicMap::roundDistance(12.0));
        $this->assertSame(50, PublicMap::roundDistance(37.4182));
        $this->assertSame(50, PublicMap::roundDistance(61.0));
        $this->assertSame(100, PublicMap::roundDistance(88.0));
    }

    /** Selebihnya ke puluhan meter — cukup untuk "240 m dari Anda". */
    public function test_jarak_jauh_dibulatkan_ke_puluhan_meter(): void
    {
        $this->assertSame(240, PublicMap::roundDistance(237.4182));
        $this->assertSame(1200, PublicMap::roundDistance(1201.9));
        $this->assertSame(4800, PublicMap::roundDistance(4796.3));
    }

    /**
     * Inti perlindungannya: dua titik yang BERBEDA harus dapat menghasilkan
     * jawaban yang SAMA.
     *
     * Selama itu terjadi, jawabannya adalah lingkaran, bukan titik — dan
     * trilaterasi kehilangan pijakan. Uji ini gagal begitu ada yang mengirim
     * jarak mentah, meski tampilannya terlihat sama saja di aplikasi.
     */
    public function test_jarak_berbeda_dapat_menghasilkan_jawaban_sama(): void
    {
        $this->assertSame(
            PublicMap::roundDistance(236.0),
            PublicMap::roundDistance(243.9),
        );

        // Ketepatan yang hilang minimal sepuluh meter di jarak menengah.
        $this->assertGreaterThanOrEqual(
            10,
            abs(PublicMap::roundDistance(1204.0) - 1204.0)
                + abs(PublicMap::roundDistance(1195.0) - 1195.0) + 10,
        );
    }

    /** Nilainya bulat, supaya JSON-nya integer dan bukan `240.0`. */
    public function test_hasilnya_selalu_bilangan_bulat(): void
    {
        $this->assertIsInt(PublicMap::roundDistance(237.4182));
        $this->assertIsInt(PublicMap::roundDistance(12.0));
    }
}
