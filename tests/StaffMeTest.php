<?php

namespace Nawasara\Aspirations\Tests;

use Nawasara\Aspirations\Http\Api\StaffMeController;
use PHPUnit\Framework\TestCase;

/**
 * GET /staff/aspirations/me — bentuk jawaban dan keputusan di dalamnya.
 *
 * Yang diuji adalah hal yang salahnya tidak menghasilkan galat: menu yang
 * muncul bagi orang yang tidak berhak, kolom yang bocor, atau petugas yang
 * dianggap bukan petugas.
 */
class StaffMeTest extends TestCase
{
    private const NIP = '199001012020121001';

    private function claims(): array
    {
        return [
            'sub' => 'kc-uuid-1',
            'preferred_username' => self::NIP,
            'name' => 'Nama Dari Token',
            'email' => 'pegawai@example.test',
        ];
    }

    private function petugas(): array
    {
        return [
            'roles' => ['lapor-opd-kabid'],
            'permissions' => ['aspirations.report.view', 'aspirations.report.verify', 'secscan.view', 'vault.read'],
            'opd' => ['id' => 12, 'name' => 'Dinas Uji'],
            'scope' => 'member',
            'is_verifier' => true,
        ];
    }

    /** ASN yang login tetapi belum diajukan: 200 yang menjelaskan, bukan 403. */
    public function test_asn_tanpa_akun_nawasara_bukan_petugas(): void
    {
        $out = StaffMeController::present($this->claims(), self::NIP, null, null);

        $this->assertFalse($out['is_petugas']);
        $this->assertSame([], $out['roles']);
        $this->assertSame([], $out['permissions']);
        $this->assertNull($out['opd']);
        $this->assertSame('none', $out['scope']);
        $this->assertFalse($out['is_verifier']);
        $this->assertSame(self::NIP, $out['nip']);
    }

    /** Petugas: peran, izin, OPD, dan cakupan dari Nawasara. */
    public function test_petugas_mendapat_peran_izin_dan_opd(): void
    {
        $out = StaffMeController::present($this->claims(), self::NIP, $this->petugas(), null);

        $this->assertTrue($out['is_petugas']);
        $this->assertTrue($out['is_verifier']);
        $this->assertSame('opd', $out['scope']);
        $this->assertSame(['id' => 12, 'name' => 'Dinas Uji'], $out['opd']);
    }

    /**
     * ⚠️ Izin paket lain TIDAK ikut keluar.
     *
     * Panel GO tidak butuh tahu bahwa seseorang memegang `vault.read`, dan
     * daftar izin lengkap adalah peta sasaran bagi siapa pun yang memegang
     * tokennya.
     */
    public function test_hanya_izin_aspirations_yang_keluar(): void
    {
        $out = StaffMeController::present($this->claims(), self::NIP, $this->petugas(), null);

        $this->assertSame(['aspirations.report.verify', 'aspirations.report.view'], $out['permissions']);
    }

    /** Akun yang perannya dicabut: barisnya ada, tetapi bukan petugas lagi. */
    public function test_akun_tanpa_izin_lihat_laporan_bukan_petugas(): void
    {
        $akun = ['roles' => [], 'permissions' => [], 'opd' => null, 'scope' => 'restricted', 'is_verifier' => false];

        $this->assertFalse(StaffMeController::present($this->claims(), self::NIP, $akun, null)['is_petugas']);
    }

    /** Inspektorat dan admin kabupaten melihat lintas OPD. */
    public function test_peran_istimewa_berlingkup_semua(): void
    {
        $akun = ['scope' => 'privileged'] + $this->petugas();

        $this->assertSame('all', StaffMeController::present($this->claims(), self::NIP, $akun, null)['scope']);
    }

    /**
     * Jabatan dari SIMAS boleh kosong.
     *
     * Direktori SIMAS belum lengkap untuk semua ASN. `simas: null` harus tetap
     * menghasilkan jawaban utuh; jabatan tidak pernah menjadi syarat akses.
     */
    public function test_simas_kosong_tetap_menjawab_utuh(): void
    {
        $out = StaffMeController::present($this->claims(), self::NIP, $this->petugas(), null);

        $this->assertNull($out['simas']);
        $this->assertTrue($out['is_petugas'], 'Akses tidak bergantung pada data SIMAS.');
    }

    /** Daftar-izin: kolom SIMAS yang tidak disebut tidak ikut keluar. */
    public function test_simas_hanya_kolom_yang_diizinkan(): void
    {
        $simas = [
            'nama' => 'NAMA SIMAS', 'nama_jabatan' => 'Kepala Bidang', 'jenis_jabatan' => 'Jabatan Struktural',
            'jenis_kepegawaian' => 'PNS', 'skpd' => 'DINAS UJI', 'unit' => 'BIDANG A',
            'synced_at' => '2026-11-20T10:00:00+00:00',
            'nik' => '3502xxxxxxxxxxxx', 'error' => 'internal',
        ];

        $out = StaffMeController::present($this->claims(), self::NIP, $this->petugas(), $simas);

        $this->assertSame(
            ['nama', 'jabatan', 'jenis_jabatan', 'jenis_kepegawaian', 'skpd', 'unit', 'synced_at'],
            array_keys($out['simas']),
        );
        $this->assertSame('Kepala Bidang', $out['simas']['jabatan']);
    }

    /** Hanya username 18 digit yang dianggap NIP; akun layanan tidak ditebak. */
    public function test_nip_hanya_dari_username_18_digit(): void
    {
        $this->assertSame(self::NIP, StaffMeController::nipFrom(['preferred_username' => self::NIP]));
        $this->assertNull(StaffMeController::nipFrom(['preferred_username' => 'admin-uji']));
        $this->assertSame(self::NIP, StaffMeController::nipFrom(['preferred_username' => 'svc', 'nip' => self::NIP]));
        $this->assertNull(StaffMeController::nipFrom([]));
    }

    /** Nama dari token; bila token tak membawanya, dari SIMAS. */
    public function test_nama_jatuh_ke_simas_bila_token_tanpa_nama(): void
    {
        $claims = $this->claims();
        unset($claims['name']);

        $out = StaffMeController::present($claims, self::NIP, null, ['nama' => 'NAMA SIMAS']);

        $this->assertSame('NAMA SIMAS', $out['name']);
    }
}
