{{--
    Umpan balik warga — penilaian bintang & "Saya Juga Mengalami".

    Keduanya sudah lama dikirim aplikasi warga dan tersimpan di basis data,
    tetapi tidak pernah ada halaman yang menampilkannya. Akibatnya petugas
    tidak pernah tahu warga menilai pekerjaannya berapa, dan pimpinan tidak
    tahu laporan mana yang dialami banyak orang sekaligus.

    Komponen anonim (tanpa Livewire): tidak ada aksi apa pun di sini, hanya
    dua angka yang sudah ada di baris laporannya.
--}}
@props(['report'])

@php
    $adaPenilaian = $report->rating !== null;
    $masaPenilaianTutup = $report->rated_closed_at !== null;
    $dukungan = (int) ($report->support_count ?? 0);
@endphp

<x-nawasara-ui::page.card>
    <h3 class="mb-4 text-sm font-semibold text-neutral-800 dark:text-neutral-100">
        Umpan Balik Warga
    </h3>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <p class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
                Penilaian Pelapor
            </p>

            @if ($adaPenilaian)
                <div class="mt-1.5 flex items-center gap-1.5">
                    @for ($i = 1; $i <= 5; $i++)
                        @if ($i <= $report->rating)
                            {{-- Sengaja TANPA varian dark: emas terisi sudah
                                 terbaca di kedua latar, dan meredupkannya di
                                 mode gelap justru membuat bintang terisi sulit
                                 dibedakan dari yang kosong. --}}
                            <x-lucide-star class="size-5 fill-amber-400 text-amber-400" />
                        @else
                            <x-lucide-star class="size-5 text-neutral-300 dark:text-neutral-600" />
                        @endif
                    @endfor

                    <span class="ml-1 text-sm font-medium text-neutral-800 dark:text-neutral-100">
                        {{ $report->rating }}/5
                    </span>
                </div>
            @elseif ($masaPenilaianTutup)
                {{-- Dibedakan dengan sengaja dari "belum menilai".
                     Nilai bawaan bagi laporan tak-dinilai akan mencampur
                     pendapat warga dengan angka yang tak pernah dikatakan
                     siapa pun — dan rerata itulah yang dibaca pimpinan. --}}
                <p class="mt-1.5 text-sm text-neutral-500 dark:text-neutral-400">
                    Tidak dinilai — masa penilaian sudah ditutup
                </p>
            @else
                <p class="mt-1.5 text-sm text-neutral-500 dark:text-neutral-400">
                    Menunggu penilaian warga
                </p>
            @endif
        </div>

        <div>
            <p class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
                Saya Juga Mengalami
            </p>

            <div class="mt-1.5 flex items-center gap-2">
                <x-lucide-users class="size-5 text-neutral-400 dark:text-neutral-500" />
                <span class="text-sm font-medium text-neutral-800 dark:text-neutral-100">
                    {{ number_format($dukungan, 0, ',', '.') }} warga
                </span>
            </div>

            @if ($dukungan >= 5)
                {{-- Ambang 5 bukan hiasan: laporan yang dialami banyak orang
                     bukan lagi keluhan perorangan, dan itu yang membedakan
                     lubang di gang rumah dari lubang di jalan yang dilewati
                     satu kelurahan. --}}
                <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">
                    Dialami banyak warga — pertimbangkan prioritas lebih tinggi.
                </p>
            @endif
        </div>
    </div>
</x-nawasara-ui::page.card>
