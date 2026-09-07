<?php

namespace Nawasara\Aspirations\Livewire\Report;

use Livewire\Component;

/**
 * Halaman daftar laporan — kerangka tipis.
 *
 * Isi tabelnya, penyaringnya, dan paginasinya ada di [Section\Table]. Dipecah
 * mengikuti §1b AGENTS.md: komponen yang menggabungkan tabel + penyaring +
 * paginasi dalam satu berkas memuat ulang seluruh keadaan halaman tiap kali
 * satu penyaring berubah.
 *
 * ⚠️ Report memakai ScopedToOpd. Daftarnya otomatis ter-scope ke OPD pembaca;
 * hanya peran istimewa yang melihat lintas-OPD.
 */
class Index extends Component
{
    public function mount(): void
    {
        $this->authorize('aspirations.report.view');
    }

    public function render()
    {
        return view('nawasara-aspirations::livewire.pages.report.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
