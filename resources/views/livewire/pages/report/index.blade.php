@php
    $statusLabels = [
        'submitted' => ['Terkirim', 'neutral'],
        'dispatched' => ['Diteruskan ke OPD', 'info'],
        'in_progress' => ['Sedang Ditangani', 'info'],
        'awaiting_verification' => ['Menunggu Pemeriksaan', 'warning'],
        'resolved' => ['Selesai', 'success'],
        'rejected' => ['Belum Dapat Diproses', 'neutral'],
    ];
@endphp

<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Ponorogo Hub', 'url' => '#'], ['label' => 'Laporan Masuk']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header
            title="Laporan Masuk"
            description="Pantau laporan warga. Penanganan laporan dilakukan di panel OPD."
            :count="$reports->total()">
        </x-nawasara-ui::page-header>

        {{-- Penyaring --}}
        <x-nawasara-ui::page.card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <x-nawasara-ui::search-input
                        wire:model.live.debounce.400ms="search"
                        placeholder="Cari kode atau judul..." />
                </div>
                <div>
                    <x-nawasara-ui::form.select
                        wire:model.live="status"
                        placeholder="Semua status"
                        :options="collect($statusLabels)->map(fn ($v) => $v[0])->all()" />
                </div>
                <div>
                    <x-nawasara-ui::form.select
                        wire:model.live="category"
                        placeholder="Semua kategori"
                        :options="$categories->pluck('name', 'id')->all()" />
                </div>
                <div class="flex items-center gap-3">
                    <x-nawasara-ui::form.checkbox
                        wire:model.live="overdue"
                        label="Lewat batas waktu" />

                    @if ($search !== '' || $status !== '' || $category !== '' || $overdue)
                        <button type="button" wire:click="clearFilters"
                            class="text-sm text-neutral-500 underline hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200">
                            Bersihkan
                        </button>
                    @endif
                </div>
            </div>
        </x-nawasara-ui::page.card>

        @if ($reports->isEmpty())
            <x-nawasara-ui::empty-state
                icon="lucide-inbox"
                title="Tidak ada laporan"
                description="Belum ada laporan yang cocok dengan penyaring yang dipilih." />
        @else
            <x-nawasara-ui::table :headers="['Kode', 'Judul', 'Kategori', 'OPD', 'Status', 'Batas Waktu', 'Dikirim']">
                @foreach ($reports as $report)
                    <tr wire:key="report-{{ $report->code }}">
                        <td class="px-6 py-4 text-sm font-medium text-neutral-800 dark:text-neutral-100">
                            {{ $report->code }}
                        </td>

                        <td class="px-6 py-4 text-sm text-neutral-700 dark:text-neutral-200">
                            <span class="line-clamp-1">{{ $report->title }}</span>
                            @if ($report->is_anonymous)
                                <span class="text-xs text-neutral-500 dark:text-neutral-400">anonim</span>
                            @endif
                        </td>

                        <td class="px-6 py-4 text-sm text-neutral-600 dark:text-neutral-300">
                            {{ $report->category?->name ?? '—' }}
                        </td>

                        <td class="px-6 py-4 text-sm text-neutral-600 dark:text-neutral-300">
                            {{ $report->opd?->name ?? '—' }}
                        </td>

                        <td class="px-6 py-4 text-sm">
                            @php($meta = $statusLabels[$report->status] ?? [$report->status, 'neutral'])
                            <x-nawasara-ui::badge :color="$meta[1]">{{ $meta[0] }}</x-nawasara-ui::badge>
                        </td>

                        <td class="px-6 py-4 text-sm">
                            @if ($report->sla_due_at === null)
                                <span class="text-neutral-400 dark:text-neutral-500">—</span>
                            @elseif ($report->isResolutionOverdue())
                                <span class="text-rose-600 dark:text-rose-400">
                                    lewat {{ $report->sla_due_at->diffForHumans(null, true) }}
                                </span>
                            @else
                                <span class="text-neutral-600 dark:text-neutral-300">
                                    {{ $report->sla_due_at->format('d M Y') }}
                                </span>
                            @endif
                        </td>

                        <td class="px-6 py-4 text-sm text-neutral-500 dark:text-neutral-400">
                            {{ $report->created_at?->format('d M Y') }}
                        </td>
                    </tr>
                @endforeach
            </x-nawasara-ui::table>

            <div class="mt-4">
                {{ $reports->links() }}
            </div>
        @endif
    </x-nawasara-ui::page.container>
</div>
