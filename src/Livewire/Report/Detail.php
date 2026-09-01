<?php

declare(strict_types=1);

namespace Nawasara\Aspirations\Livewire\Report;

use Livewire\Attributes\On;
use Livewire\Component;
use Nawasara\Aspirations\Models\Report;

/**
 * Halaman satu laporan — kerangka tipis.
 *
 * Tidak memuat aksi apa pun; penanganan ada di Section\Handling, riwayat di
 * Section\Timeline. Yang di sini hanya memuat laporannya dan menyusun
 * panel-panelnya.
 *
 * Laporan dicari lewat KODE (`LB-2026-09-0001`), bukan id berurutan. Kode
 * itu yang dipegang warga dan disebut di percakapan, dan id berurutan di URL
 * mengundang orang menebak milik orang lain.
 */
class Detail extends Component
{
    public Report $report;

    public function mount(string $code): void
    {
        $this->authorize('aspirations.report.view');

        // Global scope ScopedToOpd tetap berlaku, jadi laporan milik OPD lain
        // menghasilkan 404 — bukan halaman kosong yang terbaca seperti data
        // hilang.
        $this->report = Report::query()
            ->with(['category', 'opd', 'verifier', 'responder'])
            ->where('code', $code)
            ->firstOrFail();
    }

    /**
     * Panel penanganan menyiarkan ini setelah setiap aksi.
     *
     * Diambil ulang, bukan sekadar refresh: status berubah di component lain,
     * dan Livewire menghidupkan model ini dari snapshot permintaan sebelumnya.
     */
    #[On('report-updated')]
    public function reload(): void
    {
        $this->report = Report::query()
            ->with(['category', 'opd', 'verifier', 'responder'])
            ->whereKey($this->report->getKey())
            ->firstOrFail();
    }

    public function render()
    {
        return view('nawasara-aspirations::livewire.pages.report.detail')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
