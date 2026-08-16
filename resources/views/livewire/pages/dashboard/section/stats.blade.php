@php
    $s = $this->summary;
    $statusLabels = [
        'submitted' => 'Terkirim',
        'dispatched' => 'Diteruskan ke OPD',
        'in_progress' => 'Sedang Ditangani',
        'awaiting_verification' => 'Menunggu Pemeriksaan',
        'resolved' => 'Selesai',
        'rejected' => 'Belum Dapat Diproses',
    ];
@endphp

<div class="space-y-6">

    {{-- Pemilih rentang --}}
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-sm text-neutral-500 dark:text-neutral-400">Rentang:</span>
        @foreach ([7 => '7 Hari', 30 => '30 Hari', 90 => '90 Hari', 0 => 'Semua'] as $d => $label)
            <button type="button" wire:click="setRange({{ $d }})"
                class="rounded-lg px-3 py-1.5 text-sm font-medium transition
                    {{ $days === $d
                        ? 'bg-emerald-600 text-white'
                        : 'bg-neutral-100 text-neutral-700 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($s['total'] === 0)
        <x-nawasara-ui::empty-state
            icon="lucide-inbox"
            title="Belum ada laporan"
            description="Laporan warga akan muncul di sini setelah aplikasi GO dipakai." />
    @else
        {{-- Angka utama --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <x-nawasara-ui::stat-card compact
                label="Total Laporan" :value="number_format($s['total'])" icon="lucide-inbox" color="neutral" />

            <x-nawasara-ui::stat-card compact
                label="Sedang Berjalan" :value="number_format($s['open'])" icon="lucide-loader" color="info" />

            <x-nawasara-ui::stat-card compact
                label="Selesai" :value="number_format($s['resolved'])" icon="lucide-check-circle" color="success" />

            {{-- Yang paling perlu dilihat: tunggakan yang tenggatnya sudah lewat. --}}
            <x-nawasara-ui::stat-card compact
                label="Lewat Batas Waktu" :value="number_format($s['overdue'])"
                icon="lucide-alarm-clock" :color="$s['overdue'] > 0 ? 'danger' : 'success'" />

            <x-nawasara-ui::stat-card compact
                label="Menunggu Kabid" :value="number_format($s['awaiting_verification'])"
                icon="lucide-user-check" color="warning" />
        </div>

        <div class="grid gap-6 lg:grid-cols-3">

            {{-- Sebaran status --}}
            <x-nawasara-ui::page.card class="lg:col-span-2">
                <h3 class="text-base font-semibold text-neutral-800 dark:text-neutral-100">
                    Sebaran Status
                </h3>

                <div class="mt-4 space-y-3">
                    @foreach ($statusLabels as $key => $label)
                        @php
                            $jumlah = $this->byStatus[$key] ?? 0;
                            $persen = $s['total'] > 0 ? round($jumlah / $s['total'] * 100) : 0;
                        @endphp
                        <div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-neutral-700 dark:text-neutral-200">{{ $label }}</span>
                                <span class="font-medium text-neutral-800 dark:text-neutral-100">
                                    {{ number_format($jumlah) }}
                                    <span class="text-xs text-neutral-500 dark:text-neutral-400">({{ $persen }}%)</span>
                                </span>
                            </div>
                            <div class="mt-1 h-2 overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800">
                                <div class="h-full rounded-full
                                    {{ $key === 'resolved' ? 'bg-emerald-500' : ($key === 'rejected' ? 'bg-neutral-400' : 'bg-sky-500') }}"
                                    style="width: {{ $persen }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-nawasara-ui::page.card>

            {{-- Kepatuhan & penilaian --}}
            <x-nawasara-ui::page.card>
                <h3 class="text-base font-semibold text-neutral-800 dark:text-neutral-100">
                    Kepatuhan &amp; Penilaian
                </h3>

                <div class="mt-4 space-y-5">
                    <div>
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">Dalam Batas Waktu</p>
                        <p class="mt-1 text-3xl font-semibold
                            {{ ($s['compliance'] ?? 100) >= 80 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            {{ $s['compliance'] !== null ? $s['compliance'].'%' : '—' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">Rata-rata Penilaian Warga</p>
                        @if ($s['rating'] !== null)
                            <p class="mt-1 text-3xl font-semibold text-amber-600 dark:text-amber-400">
                                {{ $s['rating'] }}
                                <span class="text-base font-normal text-neutral-500 dark:text-neutral-400">/ 5</span>
                            </p>
                        @else
                            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                                Belum ada laporan yang dinilai.
                            </p>
                        @endif
                    </div>
                </div>
            </x-nawasara-ui::page.card>
        </div>

        {{-- OPD dengan tunggakan terbanyak --}}
        @if (count($this->byOpd) > 0)
            <x-nawasara-ui::page.card>
                <h3 class="text-base font-semibold text-neutral-800 dark:text-neutral-100">
                    OPD dengan Tunggakan Terbanyak
                </h3>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    Diurutkan menurut laporan yang sudah lewat batas waktu, bukan menurut jumlah.
                </p>

                <div class="mt-4 space-y-3">
                    @foreach ($this->byOpd as $opd)
                        <div class="flex items-center justify-between border-b border-neutral-100 pb-3 last:border-0 last:pb-0 dark:border-neutral-700">
                            <span class="text-sm text-neutral-700 dark:text-neutral-200">{{ $opd['name'] }}</span>
                            <span class="flex items-center gap-3 text-sm">
                                <span class="text-neutral-500 dark:text-neutral-400">
                                    {{ number_format($opd['jumlah']) }} laporan
                                </span>
                                @if ($opd['terlambat'] > 0)
                                    <x-nawasara-ui::badge color="danger">
                                        {{ $opd['terlambat'] }} lewat batas
                                    </x-nawasara-ui::badge>
                                @else
                                    <x-nawasara-ui::badge color="success">tepat waktu</x-nawasara-ui::badge>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </x-nawasara-ui::page.card>
        @endif
    @endif
</div>
