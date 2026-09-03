<?php

namespace Nawasara\Aspirations\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Dua kolom yang dibutuhkan kartu "Laporan Saya".
 *
 * Keduanya kecil, dan keduanya menyelesaikan masalah yang sama: kartu di
 * ponsel tidak punya ruang untuk teks panjang, dan yang terpotong justru
 * bagian yang membedakan.
 */
class ReportCardFieldsTest extends TestCase
{
    /**
     * Singkatan OPD adalah nomenklatur RESMI, bukan pemenggalan kata.
     *
     * Inilah sebabnya ia dikirim server. Aplikasi sempat menyingkat sendiri
     * dengan merangkai huruf awal — dan untuk sebagian OPD hasilnya keliru,
     * sementara memperbaikinya berarti menunggu seluruh warga memperbarui
     * pemasangan aplikasinya.
     */
    public function test_singkatan_resmi_tidak_dapat_ditebak_dari_nama(): void
    {
        $tebakan = fn (string $nama) => implode('', array_map(
            fn ($k) => strtoupper($k[0]),
            array_filter(
                preg_split('/\s+/', $nama),
                fn ($k) => ! in_array(strtolower($k), ['dan', 'serta', 'yang'], true) && $k !== '',
            ),
        ));

        // Kasus nyata dari registry produksi.
        $this->assertNotSame('SATPOLPP', $tebakan('Satuan Polisi Pamong Praja'));
        $this->assertNotSame('DUKCAPIL', $tebakan('Dinas Kependudukan dan Pencatatan Sipil'));

        // Yang kebetulan cocok justru menyesatkan: ia membuat penyingkat
        // bentukan tampak bekerja sampai bertemu kasus di atas.
        $this->assertSame('DPUPKP', $tebakan('Dinas Pekerjaan Umum, Perumahan, dan Kawasan Permukiman'));
    }

    /**
     * Nama panjang terpotong pada bagian yang SAMA untuk semua OPD.
     *
     * Alasan `opd_code` ada: bukan karena panjangnya, melainkan karena yang
     * tersisa di layar tidak membedakan apa pun.
     */
    public function test_nama_panjang_terpotong_pada_bagian_yang_tidak_membedakan(): void
    {
        $muat = 42;   // ±260 dp pada kartu

        $a = 'DINAS PEKERJAAN UMUM, PERUMAHAN, DAN KAWASAN PERMUKIMAN';
        $b = 'DINAS PENDIDIKAN, KEPEMUDAAN DAN OLAHRAGA KABUPATEN';

        $this->assertGreaterThan($muat, strlen($a));
        $this->assertSame(
            substr($a, 0, 6),
            substr($b, 0, 6),
            'Keduanya diawali "DINAS " — itulah yang tersisa setelah terpotong.',
        );

        // Singkatannya justru muat, dan justru membedakan.
        foreach (['DPUPKP', 'DLH', 'Satpol PP', 'Dispendukcapil'] as $kode) {
            $this->assertLessThan($muat, strlen($kode));
        }
    }
}
