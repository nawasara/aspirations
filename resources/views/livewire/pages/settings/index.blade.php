<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Ponorogo Hub', 'url' => '#'], ['label' => 'Pengaturan Lapor']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header
            title="Pengaturan Lapor Bunda"
            description="Angka-angka di halaman ini adalah janji ke warga, bukan setelan teknis. Perubahan langsung berlaku tanpa perlu deploy.">
            <x-nawasara-ui::button color="primary" wire:click="save" wire:loading.attr="disabled">
                Simpan Perubahan
            </x-nawasara-ui::button>
        </x-nawasara-ui::page-header>

        <div class="grid gap-6 lg:grid-cols-2">

            {{-- Batas laporan warga --}}
            <x-nawasara-ui::page.card>
                <h3 class="text-base font-semibold text-neutral-800 dark:text-neutral-100">
                    Batas Laporan Warga
                </h3>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    Menahan penyalahgunaan tanpa menyulitkan warga yang benar-benar melapor.
                </p>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <x-nawasara-ui::form.input type="number" label="Laporan per Hari"
                            wire:model="values.reports_per_day" />
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            Per warga, dihitung ulang tiap tengah malam.
                        </p>
                    </div>
                    <div>
                        <x-nawasara-ui::form.input type="number" label="Foto per Laporan"
                            wire:model="values.photos_per_report" />
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            Aplikasi menyembunyikan tombol tambah setelah batas ini.
                        </p>
                    </div>
                    <div>
                        <x-nawasara-ui::form.input type="number" label="Ukuran Foto Maksimal (KB)"
                            wire:model="values.photo_max_kb" />
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            Aplikasi mengecilkan foto sebelum mengirim.
                        </p>
                    </div>
                    <div>
                        <x-nawasara-ui::form.input type="number" label="Panjang Deskripsi (Karakter)"
                            wire:model="values.description_max" />
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            Penghitung karakter di aplikasi mengikuti angka ini.
                        </p>
                    </div>
                </div>
            </x-nawasara-ui::page.card>

            {{-- Batas waktu — janji ke warga --}}
            <x-nawasara-ui::page.card>
                <h3 class="text-base font-semibold text-neutral-800 dark:text-neutral-100">
                    Batas Waktu Penanganan
                </h3>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    Inilah yang dijanjikan kepada warga saat mengirim laporan. Ubah hanya
                    setelah disepakati OPD.
                </p>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <x-nawasara-ui::form.input type="number" label="Tanggapan Pertama (Jam)"
                            wire:model="values.response_hours" />
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            Sejak laporan diterima sampai OPD menanggapi.
                        </p>
                    </div>
                    <div>
                        <x-nawasara-ui::form.input type="number" label="Verifikasi Kabid (Jam)"
                            wire:model="values.verification_hours" />
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            Sejak petugas menyerahkan hasil sampai Kabid memeriksa.
                        </p>
                    </div>
                    <div>
                        <x-nawasara-ui::form.input type="number" label="Tutup Otomatis (Hari)"
                            wire:model="values.auto_close_days" />
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            Menunggu penilaian warga sebelum laporan ditutup.
                        </p>
                    </div>
                    <div>
                        <x-nawasara-ui::form.input type="number" label="Bintang Pembuka Ulang"
                            wire:model="values.reopen_threshold" />
                        <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                            Nilai ini ke bawah membuka kembali laporan.
                        </p>
                    </div>
                </div>
            </x-nawasara-ui::page.card>

            {{-- Deteksi laporan ganda --}}
            <x-nawasara-ui::page.card>
                <h3 class="text-base font-semibold text-neutral-800 dark:text-neutral-100">
                    Deteksi Laporan Ganda
                </h3>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    Dua laporan dianggap sama bila berada dalam jarak dan rentang waktu ini.
                    Warga ditawari "Saya Juga Mengalami", bukan dihalangi mengirim.
                </p>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <x-nawasara-ui::form.input type="number" label="Radius (Meter)"
                            wire:model="values.duplicate_radius" />
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            Terlalu lebar akan menggabungkan laporan yang berbeda.
                        </p>
                    </div>
                    <div>
                        <x-nawasara-ui::form.input type="number" label="Rentang (Hari)"
                            wire:model="values.duplicate_days" />
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            Laporan lebih lama dari ini dianggap kejadian baru.
                        </p>
                    </div>
                </div>
            </x-nawasara-ui::page.card>

            {{-- Catatan --}}
            <x-nawasara-ui::page.card>
                <h3 class="text-base font-semibold text-neutral-800 dark:text-neutral-100">
                    Yang Perlu Diketahui
                </h3>
                <ul class="mt-3 space-y-2 text-sm text-neutral-600 dark:text-neutral-300">
                    <li class="flex gap-2">
                        <span class="text-emerald-600 dark:text-emerald-400">&bull;</span>
                        Batas waktu per kategori diatur di halaman
                        <strong>Kategori Laporan</strong>, bukan di sini. Angka di halaman ini
                        berlaku umum.
                    </li>
                    <li class="flex gap-2">
                        <span class="text-emerald-600 dark:text-emerald-400">&bull;</span>
                        Perubahan berlaku untuk laporan <strong>baru</strong>. Laporan yang
                        sudah berjalan tetap memakai janji yang diterimanya saat dikirim.
                    </li>
                    <li class="flex gap-2">
                        <span class="text-amber-600 dark:text-amber-400">&bull;</span>
                        Nilai kosong atau nol akan kembali ke bawaan sistem — bukan berarti
                        batasnya dimatikan.
                    </li>
                </ul>
            </x-nawasara-ui::page.card>

        </div>
    </x-nawasara-ui::page.container>
</div>
