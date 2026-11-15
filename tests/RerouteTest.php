<?php

namespace Nawasara\Aspirations\Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Nawasara\Aspirations\Exceptions\WorkflowException;
use Nawasara\Aspirations\Models\Report;
use Nawasara\Aspirations\Services\ReportWorkflow;
use Nawasara\Registry\Models\Opd;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pengalihan laporan ke OPD lain (15 Nov 2026).
 *
 * Penjagaan diuji tanpa basis data karena semuanya berjalan sebelum transaksi.
 * Keputusan yang tidak terlihat di sini tetapi tertulis di
 * ReportWorkflow::reroute: SLA tidak dimulai ulang, warga diberi tahu, alasan
 * menjadi catatan internal.
 */
class RerouteTest extends TestCase
{
    private function workflow(): ReportWorkflow
    {
        return (new \ReflectionClass(ReportWorkflow::class))->newInstanceWithoutConstructor();
    }

    private function report(string $status, int $opdId = 1): Report
    {
        $report = new Report;
        $report->status = $status;
        $report->opd_id = $opdId;

        return $report;
    }

    private function opd(int $id): Opd
    {
        $opd = new Opd;
        $opd->id = $id;
        $opd->name = 'Dinas Uji';

        return $opd;
    }

    public function test_alasan_wajib(): void
    {
        $this->expectException(WorkflowException::class);
        $this->workflow()->reroute($this->report(Report::STATUS_DISPATCHED), $this->createStub(Authenticatable::class), $this->opd(2), '  ');
    }

    #[DataProvider('statusTertutup')]
    public function test_hanya_sebelum_diserahkan_ke_kabid(string $status): void
    {
        $this->expectException(WorkflowException::class);
        $this->workflow()->reroute($this->report($status), $this->createStub(Authenticatable::class), $this->opd(2), 'Salah dinas.');
    }

    public static function statusTertutup(): array
    {
        return [
            'menunggu pemeriksaan' => [Report::STATUS_AWAITING_VERIFICATION],
            'selesai' => [Report::STATUS_RESOLVED],
            'ditolak' => [Report::STATUS_REJECTED],
            'baru masuk, belum diteruskan' => [Report::STATUS_SUBMITTED],
        ];
    }

    public function test_tujuan_tidak_boleh_opd_yang_sama(): void
    {
        $this->expectException(WorkflowException::class);
        $this->workflow()->reroute($this->report(Report::STATUS_IN_PROGRESS, 7), $this->createStub(Authenticatable::class), $this->opd(7), 'Salah dinas.');
    }
}
