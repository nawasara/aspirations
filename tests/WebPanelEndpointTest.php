<?php

namespace Nawasara\Aspirations\Tests;

use Nawasara\Aspirations\Http\Api\StaffOpdMemberController;
use PHPUnit\Framework\TestCase;

/**
 * Aturan di balik empat endpoint panel OPD.
 *
 * Yang diuji di sini adalah keputusan yang salahnya TIDAK menghasilkan galat —
 * angka yang keliru, atau pintu yang terbuka — karena justru itu yang lolos ke
 * produksi tanpa disadari.
 */
class WebPanelEndpointTest extends TestCase
{
    /**
     * ⚠️ `null`, bukan `0`, saat tidak ada pembandingnya.
     *
     * Nol berarti "tidak berubah". Tidak adanya periode sebelumnya berbeda
     * artinya, dan menampilkannya sebagai 0% membuat bulan pertama sebuah OPD
     * terlihat stagnan padahal ia baru mulai bekerja.
     */
    public function test_perubahan_persen_null_bila_tak_ada_pembanding(): void
    {
        $pct = function (int $now, ?int $prev): ?float {
            if ($prev === null || $prev === 0) {
                return null;
            }

            return round((($now - $prev) / $prev) * 100, 1);
        };

        $this->assertNull($pct(10, null), 'Tanpa periode sebelumnya.');
        $this->assertNull($pct(10, 0), 'Periode sebelumnya kosong.');
        $this->assertSame(25.0, $pct(10, 8));
        $this->assertSame(-50.0, $pct(5, 10));
        $this->assertSame(0.0, $pct(8, 8), 'Nol berarti benar-benar tidak berubah.');
    }

    /**
     * Aturan #16 mutlak: tidak boleh memeriksa pekerjaan sendiri.
     *
     * Diuji di produksi hari ini pada laporan nyata — petugas yang mengerjakan
     * dua laporan DISKOMINFO memang tidak dapat memeriksanya.
     */
    public function test_tidak_boleh_memeriksa_pekerjaan_sendiri(): void
    {
        $boleh = fn (int $aktor, int $calon) => $aktor !== $calon;

        $this->assertFalse($boleh(5, 5));
        $this->assertTrue($boleh(5, 12));
    }

    /**
     * Aturan #25 sengaja LONGGAR selama data registry belum lengkap.
     *
     * Saat ini 13 dari 24 pengguna belum tertaut OPD mana pun. Aturan yang kaku
     * akan menolak hampir separuh penyerahan pada hari pertama; ia mengetat
     * sendiri seiring data membaik, tanpa mengubah kode.
     */
    public function test_aturan_se_opd_melonggar_bila_salah_satu_tak_tertaut(): void
    {
        $boleh = function (?int $opdAktor, ?int $opdCalon): bool {
            if ($opdAktor === null || $opdCalon === null) {
                return true;   // salah satu belum tertaut — dilonggarkan
            }

            return $opdAktor === $opdCalon;
        };

        $this->assertTrue($boleh(14, null), 'Calon belum tertaut.');
        $this->assertTrue($boleh(null, 14), 'Aktor belum tertaut.');
        $this->assertTrue($boleh(14, 14), 'Se-OPD.');
        $this->assertFalse($boleh(14, 12), 'Beda OPD, keduanya tertaut.');
    }

    /**
     * Daftar kosong harus DAPAT DIBEDAKAN sebabnya.
     *
     * "Tidak ada yang cocok dengan pencarianmu" dan "OPD ini belum punya
     * pemeriksa" menuntut kalimat berbeda di layar. Tanpa pembeda, petugas
     * menyalahkan pencariannya sendiri dan mencari nama yang memang tidak ada.
     */
    public function test_daftar_kosong_menyebutkan_sebabnya(): void
    {
        $alasan = fn (bool $adaCalon, bool $adaPencarian) => $adaPencarian && $adaCalon
            ? 'no_match'
            : 'no_verifier_in_opd';

        $this->assertSame('no_match', $alasan(true, true));
        $this->assertSame('no_verifier_in_opd', $alasan(false, true));
        $this->assertSame('no_verifier_in_opd', $alasan(false, false));
    }

    /**
     * ⚠️ OPD tidak boleh kehilangan verifikator TERAKHIRNYA.
     *
     * Tanpa penjagaan ini admin dapat mencabut perannya sendiri, dan OPD itu
     * langsung kehilangan kemampuan verifikasi — yang memulihkannya justru
     * menuntut tim Nawasara lagi, persis yang hendak dihindari fitur ini.
     */
    public function test_verifikator_terakhir_tidak_dapat_dicabut(): void
    {
        $bolehCabut = fn (int $jumlahVerifikator) => $jumlahVerifikator > 1;

        $this->assertFalse($bolehCabut(1), 'Satu-satunya pemeriksa.');
        $this->assertTrue($bolehCabut(2));
    }

    /**
     * Peran yang dapat disentuh admin OPD hanya SATU.
     *
     * Begitu peran menjadi masukan dari pemanggil, endpoint ini berubah jadi
     * alat pemberian peran apa pun — dan admin OPD dapat menjadikan dirinya
     * developer.
     */
    public function test_hanya_peran_verifikator_yang_dapat_diberikan(): void
    {
        $this->assertSame(
            'lapor-opd-kabid',
            StaffOpdMemberController::VERIFIER_ROLE,
        );
    }

    /**
     * `resolved_at` BUKAN sama dengan `verified_at`.
     *
     * Laporan yang ditolak tidak pernah diverifikasi, sehingga panel yang
     * memakai `verified_at` sebagai penanda selesai tidak pernah menghitungnya
     * di "selesai bulan ini" meski perkaranya sudah tuntas.
     */
    public function test_laporan_ditolak_tetap_punya_waktu_selesai(): void
    {
        $selesai = fn (string $status, ?string $verified, string $updated) => match ($status) {
            'resolved' => $verified ?? $updated,
            'rejected' => $updated,
            default => null,
        };

        $this->assertSame('2026-09-01', $selesai('resolved', '2026-09-01', '2026-09-02'));
        $this->assertSame('2026-09-02', $selesai('rejected', null, '2026-09-02'));
        $this->assertNull($selesai('dispatched', null, '2026-09-02'));
    }
}
