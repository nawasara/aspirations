<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Ponorogo Hub', 'url' => '#'], ['label' => 'Lapor Bunda']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header
            title="Lapor Bunda"
            description="Pantau laporan warga dan kepatuhan batas waktu penanganan.">
        </x-nawasara-ui::page-header>

        <livewire:nawasara-aspirations.dashboard.section.stats />
    </x-nawasara-ui::page.container>
</div>
