<?php

namespace Nawasara\Aspirations\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Ekspor CSV laporan warga.
 *
 * Berkas yang diunduh dibuka di Excel oleh staf OPD, dan dua hal di bawah ini
 * rusak DIAM-DIAM di sana: keduanya tetap menghasilkan berkas yang terunduh,
 * berukuran wajar, dan baru terlihat salah setelah dibuka.
 */
class ReportExportCsvTest extends TestCase
{
    /** Menulis satu baris persis seperti Table::export() menulisnya. */
    private function tulis(array $baris): string
    {
        $out = fopen('php://memory', 'r+');
        fwrite($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, $baris, ',', '"', '');
        rewind($out);
        $isi = stream_get_contents($out);
        fclose($out);

        return $isi;
    }

    /**
     * BOM UTF-8 wajib ada di awal berkas.
     *
     * Tanpanya Excel di Windows menebak encoding memakai codepage lokal, dan
     * nama kecamatan Ponorogo yang beraksen — juga "Ngebel", "Jenangan" saat
     * ditulis dengan tanda baca — tampil rusak. Berkasnya tetap terunduh dan
     * tetap terbuka, jadi tidak ada yang menyadarinya sampai staf mengeluh
     * datanya "aneh".
     */
    public function test_berkas_diawali_bom_utf8(): void
    {
        $isi = $this->tulis(['Kode', 'Kecamatan']);

        $this->assertStringStartsWith(chr(0xEF).chr(0xBB).chr(0xBF), $isi);
    }

    /**
     * Backslash di alamat TIDAK boleh diperlakukan sebagai escape.
     *
     * Nilai bawaan `$escape` pada fputcsv adalah "\\", dan dengan itu alamat
     * seperti `Jl. Raya \ Gg. Melati` — yang benar-benar ditulis warga —
     * tersimpan salah: pembatas kutipnya ikut termakan sehingga kolom di
     * kanannya bergeser. PHP 8.4 mendeprekasi nilai bawaan itu justru karena
     * hal ini; ekspor ini menyatakan '' secara tegas.
     */
    public function test_backslash_pada_alamat_tidak_menggeser_kolom(): void
    {
        $alamat = 'Jl. Raya \ Gg. Melati';
        $isi = $this->tulis(['LB-2026-09-0001', $alamat, 'Babadan']);

        $csv = substr($isi, 3);          // buang BOM
        $kolom = str_getcsv(trim($csv), ',', '"', '');

        $this->assertCount(3, $kolom, 'kolom bergeser — backslash termakan escape');
        $this->assertSame($alamat, $kolom[1]);
        $this->assertSame('Babadan', $kolom[2]);
    }

    /**
     * Judul laporan sering memuat koma dan tanda kutip.
     *
     * "Jalan rusak di RT 02, RW 05" akan pecah jadi dua kolom kalau tidak
     * dikutip — dan yang bergeser bukan cuma judulnya, melainkan SELURUH kolom
     * di kanannya, sehingga status satu laporan terbaca sebagai status laporan
     * lain.
     */
    public function test_koma_dan_kutip_dalam_judul_tetap_satu_kolom(): void
    {
        $judul = 'Jalan rusak di RT 02, RW 05 dekat "Warung Bu Sri"';
        $isi = $this->tulis(['LB-2026-09-0002', $judul, 'Selesai']);

        $kolom = str_getcsv(trim(substr($isi, 3)), ',', '"', '');

        $this->assertCount(3, $kolom);
        $this->assertSame($judul, $kolom[1]);
        $this->assertSame('Selesai', $kolom[2], 'kolom status bergeser');
    }

    /**
     * Deskripsi berbaris banyak tetap satu baris logis.
     *
     * Warga menekan enter saat menulis. Kalau baris barunya lolos mentah,
     * jumlah baris CSV tidak lagi sama dengan jumlah laporan — dan rekap yang
     * dihitung dengan menghitung baris menjadi salah.
     */
    public function test_deskripsi_berbaris_banyak_tetap_satu_baris_logis(): void
    {
        $deskripsi = "Baris pertama.\nBaris kedua.";
        $isi = $this->tulis(['LB-2026-09-0003', $deskripsi, 'Masuk']);

        $kolom = str_getcsv(trim(substr($isi, 3)), ',', '"', '');

        $this->assertCount(3, $kolom);
        $this->assertSame($deskripsi, $kolom[1]);
        $this->assertSame('Masuk', $kolom[2]);
    }
}
