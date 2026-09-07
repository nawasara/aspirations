<div>
    @php
        $kindReport = \Nawasara\Aspirations\Models\Attachment::KIND_REPORT;
        $kindEvidence = \Nawasara\Aspirations\Models\Attachment::KIND_EVIDENCE;

        $groups = $this->groups;
        $panels = [
            [$kindReport, 'Foto Laporan', 'Dikirim warga saat melapor.'],
            [$kindEvidence, 'Foto Bukti Tindak Lanjut', 'Dikirim petugas sebagai bukti pengerjaan.'],
        ];
    @endphp

    @foreach ($panels as [$kind, $judul, $keterangan])
        @continue(count($groups[$kind]) === 0)

        <x-nawasara-ui::page.card class="mb-4">
            <div class="mb-4 flex items-baseline justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-100">
                        {{ $judul }}
                    </h3>
                    <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                        {{ $keterangan }}
                    </p>
                </div>

                <x-nawasara-ui::badge color="neutral">
                    {{ count($groups[$kind]) }} foto
                </x-nawasara-ui::badge>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($groups[$kind] as $foto)
                    <div class="group relative">
                        @if ($foto['url'])
                            {{-- Dibuka di tab baru, bukan lightbox: staf sering
                                 perlu memperbesar sampai plat nomor terbaca, dan
                                 penampil bawaan peramban lebih baik untuk itu. --}}
                            <a href="{{ $foto['url'] }}" target="_blank" rel="noopener"
                                class="block overflow-hidden rounded-lg border border-gray-200 dark:border-neutral-700">
                                <img src="{{ $foto['url'] }}" alt="{{ $judul }}" loading="lazy"
                                    class="h-32 w-full object-cover transition group-hover:opacity-90" />
                            </a>
                        @else
                            {{-- Sengaja TIDAK disembunyikan. Foto yang lenyap
                                 adalah hal yang perlu diketahui staf — laporan
                                 yang kehilangan buktinya tidak boleh terlihat
                                 seperti laporan yang memang tanpa foto. --}}
                            <div class="flex h-32 w-full flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-gray-300 text-center dark:border-neutral-600">
                                <x-lucide-image-off class="size-5 text-neutral-400 dark:text-neutral-500" />
                                <span class="px-2 text-xs text-neutral-500 dark:text-neutral-400">
                                    Foto tidak terbaca
                                </span>
                            </div>
                        @endif

                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                            @if ($foto['at'])
                                <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ $foto['at'] }}
                                </span>
                            @else
                                {{-- EXIF kosong itu LUMRAH — WhatsApp membuangnya.
                                     Karena itu ditulis netral, bukan sebagai
                                     peringatan. --}}
                                <span class="text-xs text-neutral-400 dark:text-neutral-500">
                                    Tanpa waktu ambil
                                </span>
                            @endif

                            @if ($foto['suspect'])
                                {{-- MENANDAI, bukan menuduh: foto bukti yang
                                     diambil sebelum laporannya masuk. Bisa jadi
                                     foto lama yang dipakai ulang — Kabid yang
                                     memutuskan, bukan sistem. --}}
                                <x-nawasara-ui::badge color="warning">Perlu dicek</x-nawasara-ui::badge>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-nawasara-ui::page.card>
    @endforeach
</div>
