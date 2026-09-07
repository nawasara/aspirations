<x-nawasara-ui::page.card>
    @php
        $R = \Nawasara\Aspirations\Models\Report::class;

        $statusLabel = [
            $R::STATUS_SUBMITTED => 'Masuk',
            $R::STATUS_DISPATCHED => 'Diteruskan',
            $R::STATUS_IN_PROGRESS => 'Dikerjakan',
            $R::STATUS_AWAITING_VERIFICATION => 'Menunggu Pemeriksaan',
            $R::STATUS_RESOLVED => 'Selesai',
            $R::STATUS_REJECTED => 'Ditolak',
        ];

        $entries = $this->entries;
    @endphp

    <h3 class="mb-4 text-sm font-semibold text-neutral-800 dark:text-neutral-100">
        Riwayat Penanganan
    </h3>

    @if ($entries->isEmpty())
        <x-nawasara-ui::empty-state
            icon="lucide-history"
            title="Belum ada riwayat"
            description="Setiap tanggapan dan perpindahan status akan tercatat di sini." />
    @else
        <ol class="space-y-4">
            @foreach ($entries as $entry)
                <li class="relative flex gap-3 pb-4 last:pb-0">
                    {{-- Garis penghubung antar titik; tidak digambar di baris
                         terakhir supaya linimasanya berujung, bukan menggantung. --}}
                    @unless ($loop->last)
                        <span aria-hidden="true"
                            class="absolute left-[7px] top-4 h-full w-px bg-gray-200 dark:bg-neutral-700"></span>
                    @endunless

                    <span class="relative z-10 mt-1.5 size-[15px] shrink-0 rounded-full border-2 border-white bg-emerald-500 dark:border-neutral-800 dark:bg-emerald-400"></span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($entry->status_to)
                                <span class="text-sm font-medium text-neutral-800 dark:text-neutral-100">
                                    {{ $statusLabel[$entry->status_to] ?? $entry->status_to }}
                                </span>

                                @if ($entry->status_from)
                                    <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                        dari {{ $statusLabel[$entry->status_from] ?? $entry->status_from }}
                                    </span>
                                @endif
                            @else
                                <span class="text-sm font-medium text-neutral-800 dark:text-neutral-100">
                                    Tanggapan
                                </span>
                            @endif

                            @if ($entry->is_internal)
                                {{-- Ditandai jelas: isinya TIDAK dilihat warga,
                                     dan petugas perlu tahu itu sebelum menulis
                                     sesuatu yang dikiranya terbaca pelapor. --}}
                                <x-nawasara-ui::badge color="warning">Catatan internal</x-nawasara-ui::badge>
                            @endif
                        </div>

                        @if ($entry->body)
                            <p class="mt-1 whitespace-pre-line text-sm text-neutral-700 dark:text-neutral-200">
                                {{ $entry->body }}
                            </p>
                        @endif

                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            {{ $entry->user?->name ?? 'Sistem' }}
                            ·
                            {{ $entry->created_at?->translatedFormat('d M Y, H:i') ?? '—' }}
                        </p>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</x-nawasara-ui::page.card>
