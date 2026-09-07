<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Ponorogo Hub', 'url' => '#'], ['label' => 'Laporan Masuk']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        {{-- Kerangka tipis — tabel, penyaring, dan paginasi ada di section.
             Lihat §1b AGENTS.md. --}}
        <livewire:nawasara-aspirations.report.section.table />
    </x-nawasara-ui::page.container>
</div>
