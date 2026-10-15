<?php

namespace Nawasara\Aspirations\Tests;

use Nawasara\Aspirations\Support\CitizenReadLimits;
use PHPUnit\Framework\TestCase;

/**
 * Batas laju membaca laporan warga lain (audit keamanan 15 Oktober 2026,
 * temuan T5).
 */
class CitizenReadLimitsTest extends TestCase
{
    public function test_tiga_batas_dengan_kunci_berbeda(): void
    {
        $limits = CitizenReadLimits::build('asp-similar', 'sub-1', '203.0.113.5', 10, 100, 1000);

        $this->assertCount(3, $limits);

        // Kunci yang sama akan membuat Laravel menghitung ketiganya sebagai
        // satu penghitung, dan batas menit menimpa batas hari.
        $keys = array_map(fn ($l) => $l->key, $limits);
        $this->assertCount(3, array_unique($keys));

        $this->assertSame([10, 100, 1000], array_map(fn ($l) => $l->maxAttempts, $limits));
        $this->assertSame([60, 86400, 86400], array_map(fn ($l) => $l->decaySeconds, $limits));
    }

    public function test_dua_akun_tidak_berbagi_jatah_tetapi_berbagi_jatah_ip(): void
    {
        $a = CitizenReadLimits::build('asp-similar', 'sub-a', '203.0.113.5', 10, 100, 1000);
        $b = CitizenReadLimits::build('asp-similar', 'sub-b', '203.0.113.5', 10, 100, 1000);

        $this->assertNotSame($a[0]->key, $b[0]->key);
        $this->assertSame($a[2]->key, $b[2]->key);
    }

    public function test_nol_mematikan_batas(): void
    {
        $this->assertCount(1, CitizenReadLimits::build('x', 'sub', '1.1.1.1', 0, 0, 5));
        $this->assertCount(0, CitizenReadLimits::build('x', 'sub', '1.1.1.1', 0, 0, 0));
    }

    public function test_tanpa_sub_jatuh_ke_ip(): void
    {
        $limits = CitizenReadLimits::build('x', null, '203.0.113.5', 10, 0, 0);

        $this->assertStringContainsString('ip:203.0.113.5', $limits[0]->key);
    }

    public function test_pesan_dalam_bahasa_indonesia(): void
    {
        $this->assertSame('45 detik', CitizenReadLimits::humanWait(45));
        $this->assertSame('2 menit', CitizenReadLimits::humanWait(61));
        $this->assertSame('3 jam', CitizenReadLimits::humanWait(7201));
        $this->assertStringStartsWith('Terlalu banyak permintaan', CitizenReadLimits::message(30));
    }
}
