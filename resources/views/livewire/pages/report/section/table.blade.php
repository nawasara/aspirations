<div>
    @php
        $statusMeta = [
            \Nawasara\Aspirations\Models\Report::STATUS_SUBMITTED => ['Masuk', 'neutral'],
            \Nawasara\Aspirations\Models\Report::STATUS_DISPATCHED => ['Diteruskan', 'info'],
            \Nawasara\Aspirations\Models\Report::STATUS_IN_PROGRESS => ['Dikerjakan', 'warning'],
            \Nawasara\Aspirations\Models\Report::STATUS_AWAITING_VERIFICATION => ['Menunggu Pemeriksaan', 'warning'],
            \Nawasara\Aspirations\Models\Report::STATUS_RESOLVED => ['Selesai', 'success'],
            \Nawasara\Aspirations\Models\Report::STATUS_REJECTED => ['Ditolak', 'danger'],
        ];
    @endphp

    <x-nawasara-ui::page-header
        title="Laporan Masuk"
        description="Laporan warga yang ditujukan ke perangkat daerah Anda."
        :count="$this->total" />

    {{-- Toolbar — bentuk baku halaman daftar (§1a AGENTS.md).
         filter-panel meneleportasikan chip ke [data-filter-chips] di bawah,
         supaya chip yang membungkus tidak mengganggu tata letak toolbar. --}}
    <div class="space-y-2 mb-4">
        <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <x-nawasara-ui::filter-panel
                    label="Filter"
                    :state="[
                        'statusFilter' => $statusFilter,
                        'categoryFilter' => $categoryFilter,
                        'districtFilter' => $districtFilter,
                        'overdueFilter' => $overdueFilter,
                    ]"
                    :labels="[
                        'statusFilter' => $this->statusOptions,
                        'categoryFilter' => $this->categoryOptions,
                        'districtFilter' => $this->districtOptions,
                        'overdueFilter' => $this->overdueOptions,
                    ]">

                    <x-nawasara-ui::filter-group
                        label="Status" model="statusFilter"
                        :items="$this->statusOptions" icon="lucide-circle-dot" />

                    <x-nawasara-ui::filter-group
                        label="Kategori" model="categoryFilter"
                        :items="$this->categoryOptions" icon="lucide-tags" />

                    {{-- Hanya kecamatan yang benar-benar punya laporan.
                         Menawarkan 21 pilihan yang 19 di antaranya selalu
                         kosong membuat saringan ini terasa rusak. --}}
                    @if (count($this->districtOptions) > 1)
                        <x-nawasara-ui::filter-group
                            label="Kecamatan" model="districtFilter"
                            :items="$this->districtOptions" icon="lucide-map-pin" />
                    @endif

                    <x-nawasara-ui::filter-group
                        label="Batas Waktu" model="overdueFilter"
                        :items="$this->overdueOptions" icon="lucide-clock-alert" />
                </x-nawasara-ui::filter-panel>
            </div>

            {{-- ⚠️ prop `model`, BUKAN wire:model — salah satu ini membuat
                 kotaknya tidak terikat apa pun, tanpa galat. --}}
            <x-nawasara-ui::search-input model="search" placeholder="Cari kode atau judul laporan ..." />

            @can('aspirations.report.export')
                {{-- Mengunduh HASIL SARINGAN yang sedang terlihat, bukan
                     seluruh tabel — karena itu letaknya di sebelah saringan,
                     bukan di zona aksi page-header. --}}
                <x-nawasara-ui::icon-button
                    icon="download"
                    tooltip="Unduh CSV sesuai saringan"
                    placement="left"
                    wire:click="export"
                    wire:loading.attr="disabled" />
            @endcan
        </div>

        {{-- ⚠️ WAJIB — filter-panel meneleportasikan chip ke sini. Tanpanya
             chip hilang dan staf tidak tahu saringan apa yang sedang aktif. --}}
        <div data-filter-chips class="flex flex-wrap items-center gap-2"></div>
    </div>

    @if ($reports->isEmpty())
        {{-- DUA empty state, bukan satu: pesan yang sama untuk keduanya
             membuat staf mencari data yang sebenarnya ada, hanya tersaring. --}}
        @if ($search !== '' || $statusFilter !== '' || $categoryFilter !== '' || $districtFilter !== '' || $overdueFilter !== '')
            <x-nawasara-ui::empty-state
                icon="lucide-search-x"
                title="Tidak ada yang cocok"
                description="Ubah kata kunci atau saringannya." />
        @else
            <x-nawasara-ui::empty-state
                icon="lucide-inbox"
                title="Belum ada laporan"
                description="Laporan warga yang ditujukan ke perangkat daerah Anda akan muncul di sini." />
        @endif
    @else
        {{-- ⚠️ Baris WAJIB di <x-slot:table> — tanpa itu header tergambar,
             jumlahnya benar, dan barisnya kosong tanpa galat apa pun. --}}
        <x-nawasara-ui::table
            :headers="['Kode', 'Judul', 'Kategori', 'OPD', 'Kecamatan', 'Status', 'Batas Waktu', 'Dikirim', '']"
            stickyLast>
            <x-slot:table>
                @foreach ($reports as $report)
                    <tr wire:key="report-{{ $report->code }}">
                        <td class="px-6 py-4 text-sm font-medium">
                            {{-- Kode laporan jadi tautan: itu penanda yang
                                 dipegang warga dan disebut di percakapan. --}}
                            <a href="{{ route('nawasara-aspirations.reports.detail', $report->code) }}"
                                wire:navigate
                                class="text-emerald-700 transition hover:underline dark:text-emerald-400">
                                {{ $report->code }}
                            </a>
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
                            {{-- Tetap ditampilkan meski daftarnya ter-scope per
                                 OPD: peran istimewa (inspektorat, pengawas SLA)
                                 melihat lintas-OPD, dan tanpa kolom ini mereka
                                 tak tahu laporan siapa yang sedang dibaca. --}}
                            {{ $report->opd?->name ?? '—' }}
                        </td>

                        <td class="px-6 py-4 text-sm text-neutral-600 dark:text-neutral-300">
                            {{ $report->district_name ?? '—' }}
                        </td>

                        <td class="px-6 py-4 text-sm">
                            @php($meta = $statusMeta[$report->status] ?? [$report->status, 'neutral'])
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
                            {{ $report->received_at?->format('d M Y') ?? '—' }}
                        </td>

                        <td class="px-6 py-4 text-sm">
                            {{-- Dropdown tiga titik, bukan tombol berjejer:
                                 tombol berjejer memakan lebar kolom dan tidak
                                 menyisakan tempat bagi aksi ketiga. --}}
                            <x-nawasara-ui::dropdown-menu-action :id="$report->id" :items="[
                                [
                                    'type' => 'link',
                                    'label' => 'Lihat Detail',
                                    'href' => route('nawasara-aspirations.reports.detail', $report->code),
                                    'icon' => 'lucide-eye',
                                    'navigate' => true,
                                    'permission' => 'aspirations.report.view',
                                ],
                            ]" />
                        </td>
                    </tr>
                @endforeach
            </x-slot:table>
        </x-nawasara-ui::table>

        <div class="mt-4">
            {{ $reports->links() }}
        </div>
    @endif
</div>
