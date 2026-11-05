<?php

namespace Nawasara\Aspirations\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Nawasara\Aspirations\Http\Api\CitizenReportController;
use Nawasara\Aspirations\Http\Resources\OthersReportResource;
use Nawasara\Aspirations\Http\Resources\ReportResource;
use Nawasara\Aspirations\Models\Report;
use Nawasara\Aspirations\Support\LocationSource;
use PHPUnit\Framework\TestCase;

/**
 * `location_source`: membedakan titik GPS dari titik yang ditunjuk di peta.
 *
 * Tanpa pembeda, titik tunjukan tersamar sebagai titik GPS dan
 * `location_accuracy` berhenti berarti apa pun bagi petugas yang berangkat ke
 * lapangan.
 */
class LocationSourceTest extends TestCase
{
    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $factory = new Factory(new Translator(new ArrayLoader, 'id'));

        return $factory->make($data, CitizenReportController::locationRules(), CitizenReportController::storeMessages());
    }

    public function test_tanpa_kunci_tetap_diterima_seperti_aplikasi_lama(): void
    {
        $this->assertFalse($this->validate(['latitude' => -7.87, 'longitude' => 111.46, 'location_accuracy' => 8])->fails());
        $this->assertFalse($this->validate([])->fails(), 'Laporan tanpa titik tetap diterima.');
    }

    public function test_manual_dengan_akurasi_null_diterima(): void
    {
        $v = $this->validate(['latitude' => -7.8686, 'longitude' => 111.4619, 'location_accuracy' => null, 'location_source' => 'manual']);

        $this->assertFalse($v->fails());
    }

    public function test_nilai_tak_dikenal_ditolak_dengan_pesan_indonesia(): void
    {
        $v = $this->validate(['latitude' => -7.87, 'longitude' => 111.46, 'location_source' => 'wifi']);

        $this->assertTrue($v->fails());
        $this->assertSame('Asal lokasi harus gps atau manual.', $v->errors()->first('location_source'));
    }

    public function test_manual_tanpa_titik_ditolak(): void
    {
        $v = $this->validate(['location_source' => 'manual']);

        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('latitude'));
        $this->assertTrue($v->errors()->has('longitude'));
    }

    public function test_bawaan_gps_bila_ada_titik(): void
    {
        $this->assertSame(
            ['source' => 'gps', 'accuracy' => 8.5],
            LocationSource::normalize(null, -7.87, 111.46, '8.5'),
        );
    }

    public function test_tanpa_titik_tidak_bersumber(): void
    {
        $this->assertSame(['source' => null, 'accuracy' => null], LocationSource::normalize(null, null, null, null));
        $this->assertSame(['source' => null, 'accuracy' => null], LocationSource::normalize('gps', null, null, 12));
    }

    /** Angka apa pun pada titik tunjukan mengembalikan masalah yang hendak diselesaikan. */
    public function test_manual_membuang_akurasi(): void
    {
        $this->assertSame(['source' => 'manual', 'accuracy' => null], LocationSource::normalize('manual', -7.87, 111.46, 999));
        $this->assertSame(['source' => 'manual', 'accuracy' => null], LocationSource::normalize('manual', -7.87, 111.46, 0));
    }

    private function report(?string $source): Report
    {
        $report = new Report;
        $report->setRawAttributes([
            'code' => 'LB-2026-11-0001',
            'status' => 'in_progress',
            'title' => 'Jalan berlubang',
            'description' => 'Lubang besar',
            'latitude' => -7.8686,
            'longitude' => 111.4619,
            'location_accuracy' => null,
            'location_source' => $source,
            'is_anonymous' => 0,
            'keycloak_sub' => 'pemilik-sub',
            'received_at' => Carbon::parse('2026-11-01 08:00:00'),
        ]);
        $report->setRelation('districtRef', null);
        $report->setRelation('citizen', (object) ['full_name' => 'Budi']);

        return $report;
    }

    public function test_pemilik_melihat_asal_titik(): void
    {
        $data = (new ReportResource($this->report('manual')))->resolve(new Request);

        $this->assertSame('manual', $data['location']['source']);
    }

    /** Warga lain tidak menerima koordinat, jadi asal titiknya pun tidak ikut. */
    public function test_warga_lain_tidak_menerima_asal_titik(): void
    {
        $data = (new OthersReportResource($this->report('manual')))->resolve(new Request);

        $this->assertArrayNotHasKey('source', $data['location']);
    }
}
