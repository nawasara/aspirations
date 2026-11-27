<?php

namespace Nawasara\Aspirations\Tests;

use Nawasara\Aspirations\Http\Api\StaffOpdEmployeeController;
use Nawasara\Registry\Models\SimasProfile;
use PHPUnit\Framework\TestCase;

/**
 * GET /staff/aspirations/opd/employees: daftar pegawai OPD untuk admin OPD.
 *
 * Yang diuji adalah hal yang salahnya tidak menghasilkan galat: kolom yang
 * bocor, atau pegawai yang diajukan dua kali karena statusnya tidak terlihat.
 */
class OpdEmployeeDirectoryTest extends TestCase
{
    private const LABELS = [
        'lapor-opd-operator' => 'Petugas penanganan laporan',
        'lapor-opd-kabid' => 'Kabid pemeriksa',
    ];

    private function profile(array $attrs = []): SimasProfile
    {
        $p = new SimasProfile;
        // forceFill tanpa tanggal: atribut tanggal butuh koneksi DB untuk formatnya.
        $p->forceFill($attrs + [
            'nip' => '198001012005011001',
            'nama' => 'NAMA PEGAWAI',
            'nama_jabatan' => 'Kepala Bidang Bina Marga',
            'jenis_jabatan' => 'Jabatan Struktural',
            'jenis_kepegawaian' => 'PNS',
            'skpd' => 'DINAS UJI',
            'unit' => 'BIDANG BINA MARGA',
            'status' => 'ok',
            'error' => 'rahasia internal',
            'source' => 'slow',
        ]);

        return $p;
    }

    /** Daftar-izin: hanya kolom yang dibutuhkan untuk memilih dan mengajukan. */
    public function test_hanya_kolom_yang_diizinkan_yang_keluar(): void
    {
        $out = StaffOpdEmployeeController::present($this->profile(), null, null, self::LABELS);

        $this->assertSame([
            'nip', 'name', 'jabatan', 'jenis_jabatan', 'is_struktural', 'unit',
            'officer_role', 'officer_role_label', 'pending_role', 'pending_role_label', 'synced_at',
        ], array_keys($out));
        $this->assertArrayNotHasKey('error', $out);
        $this->assertArrayNotHasKey('source', $out);
    }

    /** Petugas yang sudah ada dan pengajuan yang menunggu terlihat, supaya tidak diajukan dua kali. */
    public function test_status_petugas_dan_pengajuan_terlihat(): void
    {
        $sudah = StaffOpdEmployeeController::present($this->profile(), 'lapor-opd-kabid', null, self::LABELS);
        $this->assertSame('Kabid pemeriksa', $sudah['officer_role_label']);
        $this->assertNull($sudah['pending_role']);

        $menunggu = StaffOpdEmployeeController::present($this->profile(), null, 'lapor-opd-operator', self::LABELS);
        $this->assertNull($menunggu['officer_role']);
        $this->assertSame('Petugas penanganan laporan', $menunggu['pending_role_label']);
    }

    /** Peran yang tidak lagi ada di daftar tetap tampil apa adanya, tidak hilang. */
    public function test_peran_tak_dikenal_tampil_apa_adanya(): void
    {
        $out = StaffOpdEmployeeController::present($this->profile(), 'peran-lama', null, self::LABELS);

        $this->assertSame('peran-lama', $out['officer_role_label']);
    }

    /** SIMAS menulis jenis jabatan tidak seragam. */
    public function test_struktural_dikenali_apa_pun_ejaannya(): void
    {
        $this->assertTrue(StaffOpdEmployeeController::isStruktural('Jabatan Struktural'));
        $this->assertTrue(StaffOpdEmployeeController::isStruktural('STRUKTURAL'));
        $this->assertFalse(StaffOpdEmployeeController::isStruktural('Jabatan Fungsional'));
        $this->assertFalse(StaffOpdEmployeeController::isStruktural(null));
    }
}
