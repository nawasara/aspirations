<?php

namespace Nawasara\Aspirations\Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Nawasara\Aspirations\Exceptions\WorkflowException;
use Nawasara\Aspirations\Models\Report;
use Nawasara\Aspirations\Services\ReportWorkflow;
use PHPUnit\Framework\TestCase;

/**
 * Catatan internal antar-petugas (15 Nov 2026).
 *
 * Penjagaan yang diuji berjalan SEBELUM basis data disentuh, sehingga dapat
 * diperiksa tanpa Laravel. Yang paling penting tidak terlihat di sini:
 * catatan disimpan is_internal = true dan warga memuat tanggapan lewat scope
 * public(), jadi catatan ini tidak pernah sampai ke aplikasi warga.
 */
class InternalNoteTest extends TestCase
{
    private function workflow(): ReportWorkflow
    {
        return (new \ReflectionClass(ReportWorkflow::class))->newInstanceWithoutConstructor();
    }

    private function actor(): Authenticatable
    {
        return $this->createStub(Authenticatable::class);
    }

    public function test_catatan_kosong_ditolak(): void
    {
        $report = new Report;
        $report->status = Report::STATUS_IN_PROGRESS;

        $this->expectException(WorkflowException::class);
        $this->workflow()->addInternalNote($report, $this->actor(), "   \n ");
    }

    public function test_laporan_selesai_tidak_dapat_diberi_catatan(): void
    {
        $report = new Report;
        $report->status = Report::STATUS_RESOLVED;

        $this->expectException(WorkflowException::class);
        $this->workflow()->addInternalNote($report, $this->actor(), 'Sudah dicek ulang.');
    }

    public function test_laporan_ditolak_tidak_dapat_diberi_catatan(): void
    {
        $report = new Report;
        $report->status = Report::STATUS_REJECTED;

        $this->expectException(WorkflowException::class);
        $this->workflow()->addInternalNote($report, $this->actor(), 'Catatan.');
    }
}
