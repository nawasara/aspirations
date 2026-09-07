<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[
                ['label' => 'Laporan Warga', 'url' => '#'],
                ['label' => 'Laporan Masuk', 'url' => route('nawasara-aspirations.reports')],
                ['label' => $report->code],
            ]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header
            :title="$report->title ?: $report->code"
            :description="$report->code.' · '.($report->category->name ?? 'Tanpa kategori')" />

        {{-- Bento — tiap panel component sendiri, mengikuti pola detail usulan
             di nawasara/hibah. Dipecah bukan demi kerapian: panel penanganan
             menyiarkan `report-updated` setelah tiap aksi, dan hanya panel yang
             mendengarkannya yang dirender ulang. Sebagai satu kelas, menyimpan
             satu tanggapan akan menggambar ulang seluruh halaman termasuk foto
             yang URL-nya harus ditandatangani ulang. --}}
        <div class="space-y-4">
            {{-- Ringkasan: sisa waktu + isi laporan. Paling atas karena
                 menjawab "seberapa mendesak ini" sebelum apa pun dibaca. --}}
            <livewire:nawasara-aspirations.report.section.summary
                :report="$report"
                :key="'summary-'.$report->id" />

            {{-- Foto sebelum panel penanganan: petugas memutuskan tindakan dari
                 apa yang terlihat, jadi buktinya harus sudah terbaca saat
                 tombolnya sampai di layar. --}}
            <livewire:nawasara-aspirations.report.section.photos
                :report="$report"
                :key="'photos-'.$report->id" />

            <livewire:nawasara-aspirations.report.section.handling
                :report="$report"
                :key="'handling-'.$report->id.'-'.$report->status" />

            {{-- Riwayat dan umpan balik berdampingan: keduanya bacaan, bukan
                 tindakan, dan masing-masing cukup sempit untuk setengah lebar.
                 Ditumpuk di layar kecil. --}}
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <livewire:nawasara-aspirations.report.section.timeline
                        :report="$report"
                        :key="'timeline-'.$report->id" />
                </div>

                <x-nawasara-aspirations::report-feedback :report="$report" />
            </div>
        </div>
    </x-nawasara-ui::page.container>
</div>
