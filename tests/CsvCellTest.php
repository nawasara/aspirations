<?php

namespace Nawasara\Aspirations\Tests;

use Nawasara\Aspirations\Support\CsvCell;
use PHPUnit\Framework\TestCase;

/**
 * CSV formula injection pada ekspor laporan (audit keamanan 15 Oktober 2026,
 * temuan R11).
 */
class CsvCellTest extends TestCase
{
    public function test_awalan_rumus_diberi_kutip_tunggal(): void
    {
        $this->assertSame("'=HYPERLINK(\"http://x\",\"Klik\")", CsvCell::escape('=HYPERLINK("http://x","Klik")'));
        $this->assertSame("'+1+1", CsvCell::escape('+1+1'));
        $this->assertSame("'-2+3", CsvCell::escape('-2+3'));
        $this->assertSame("'@SUM(A1)", CsvCell::escape('@SUM(A1)'));
        $this->assertSame("'\t=1", CsvCell::escape("\t=1"));
        $this->assertSame("'\r=1", CsvCell::escape("\r=1"));
    }

    public function test_teks_biasa_tidak_berubah(): void
    {
        $this->assertSame('Jalan rusak di RT 02', CsvCell::escape('Jalan rusak di RT 02'));
        $this->assertSame('LB-2026-10-0001', CsvCell::escape('LB-2026-10-0001'));
        // Tanda sama dengan di TENGAH tidak berbahaya.
        $this->assertSame('a=b', CsvCell::escape('a=b'));
        $this->assertSame('', CsvCell::escape(''));
    }

    /** Angka dan null tidak disentuh: penilaian dan dukungan tetap angka. */
    public function test_bukan_string_dibiarkan(): void
    {
        $this->assertSame(-3, CsvCell::escape(-3));
        $this->assertSame(4, CsvCell::escape(4));
        $this->assertNull(CsvCell::escape(null));
    }

    public function test_satu_baris_lewat_fputcsv(): void
    {
        $out = fopen('php://memory', 'r+');
        fputcsv($out, CsvCell::escapeRow(['LB-1', '=cmd|\' /C calc\'!A0', 5]), ',', '"', '');
        rewind($out);
        $kolom = str_getcsv(trim(stream_get_contents($out)), ',', '"', '');
        fclose($out);

        $this->assertSame("'=cmd|' /C calc'!A0", $kolom[1]);
        $this->assertSame('5', $kolom[2]);
    }
}
