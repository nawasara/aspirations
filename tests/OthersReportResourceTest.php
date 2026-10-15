<?php

namespace Nawasara\Aspirations\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Nawasara\Aspirations\Http\Resources\OthersReportResource;
use Nawasara\Aspirations\Http\Resources\ReportResource;
use Nawasara\Aspirations\Http\Resources\SimilarReportResource;
use Nawasara\Aspirations\Models\Report;
use PHPUnit\Framework\TestCase;

/**
 * Laporan warga LAIN tidak boleh membawa koordinat, alamat, atau nama pelapor
 * (audit keamanan 15 Oktober 2026, temuan T5).
 *
 * Yang diuji resource sungguhan, bukan salinannya, dengan laporan yang SENGAJA
 * berisi semua data pribadi: kebocoran hanya terlihat bila datanya ada.
 */
class OthersReportResourceTest extends TestCase
{
    private function report(array $extra = []): Report
    {
        $report = new Report;

        // setRawAttributes: tanpa koneksi basis data, cast tanggal pada
        // setAttribute() akan mencari format tanggal dari koneksi.
        $report->setRawAttributes(array_merge([
            'code' => 'LB-2026-10-0001',
            'status' => 'in_progress',
            'title' => 'Jalan berlubang',
            'description' => 'Lubang besar di depan rumah',
            'latitude' => -7.8712345,
            'longitude' => 111.4623456,
            'full_address' => 'Jl. Melati No. 7, RT 02 RW 05',
            'village' => 'Babadan',
            'district' => 'Babadan',
            'is_anonymous' => 0,
            'keycloak_sub' => 'pemilik-sub',
            'support_count' => 3,
            'received_at' => Carbon::parse('2026-10-01 08:00:00'),
        ], $extra));

        $report->setRelation('districtRef', null);
        // Pelapor TIDAK anonim dan punya nama: justru kasus yang dulu bocor.
        $report->setRelation('citizen', (object) ['full_name' => 'Budi Santoso']);

        return $report;
    }

    public function test_detail_untuk_warga_lain_tanpa_data_pribadi(): void
    {
        $data = (new OthersReportResource($this->report()))->resolve(new Request);

        $this->assertSame(['village' => 'Babadan', 'district' => 'Babadan'], $data['location']);
        $this->assertSame(OthersReportResource::MASKED_REPORTER, $data['reporter_name']);
        $this->assertArrayNotHasKey('is_anonymous', $data);

        $json = json_encode($data);
        $this->assertStringNotContainsString('7.8712', $json);
        $this->assertStringNotContainsString('111.4623', $json);
        $this->assertStringNotContainsString('Melati', $json);
        $this->assertStringNotContainsString('pemilik-sub', $json);
        $this->assertStringNotContainsString('Budi', $json);
    }

    /**
     * Kunci yang dikirim adalah bagian dari kunci ReportResource, supaya
     * aplikasi tetap memakai satu model untuk kedua bentuk.
     */
    public function test_kunci_bagian_dari_bentuk_pemilik(): void
    {
        $report = $this->report(['is_supported' => true, 'is_mine' => false]);

        $ownerData = (new ReportResource($report))->resolve(new Request);
        $owner = array_keys($ownerData);

        // Pemilik tetap melihat namanya sendiri: bukti data uji memang memuat
        // nama, sehingga ketiadaannya di bentuk warga lain bermakna.
        $this->assertSame('Budi Santoso', $ownerData['reporter_name']);
        $other = array_keys((new OthersReportResource($report))->resolve(new Request));

        $this->assertSame([], array_values(array_diff($other, $owner)));
        $this->assertContains('is_supported', $other);
    }

    public function test_laporan_serupa_hanya_isi_kartu_dan_jarak_dibulatkan(): void
    {
        $report = $this->report(['distance_meters' => 37.4]);
        $report->setRelation('category', null);

        $data = (new SimilarReportResource($report))->resolve(new Request);

        $this->assertSame(
            ['code', 'title', 'status', 'category', 'support_count', 'distance_meters'],
            array_keys($data),
        );
        $this->assertSame(50, $data['distance_meters']);
        $this->assertStringNotContainsString('7.8712', json_encode($data));
    }
}
