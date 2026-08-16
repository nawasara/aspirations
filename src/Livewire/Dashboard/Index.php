<?php

namespace Nawasara\Aspirations\Livewire\Dashboard;

use Livewire\Component;

/**
 * Halaman pantau Lapor Bunda.
 *
 * Tipis dengan sengaja — hanya kerangka. Angka-angkanya dihitung di komponen
 * anak supaya query yang berat tidak ikut berjalan ulang setiap kali ada
 * bagian lain halaman ini yang berubah.
 */
class Index extends Component
{
    public function mount(): void
    {
        $this->authorize('aspirations.dashboard.view');
    }

    public function render()
    {
        return view('nawasara-aspirations::livewire.pages.dashboard.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
