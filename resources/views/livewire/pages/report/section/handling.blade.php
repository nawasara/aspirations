<div>
    @php
        $R = \Nawasara\Aspirations\Models\Report::class;
        $status = $report->status;
        $bisaTangani = auth()->user()->can('aspirations.report.respond');
        $bisaPeriksa = auth()->user()->can('aspirations.report.verify');
    @endphp

    <x-nawasara-ui::page.card>
        <h3 class="mb-4 text-sm font-semibold text-neutral-800 dark:text-neutral-100">
            Penanganan
        </h3>

        {{-- ── Belum dikerjakan ── --}}
        @if ($status === $R::STATUS_DISPATCHED && $bisaTangani)
            <p class="mb-3 text-sm text-neutral-600 dark:text-neutral-300">
                Laporan sudah diteruskan ke OPD Anda. Tuliskan langkah pertama
                yang diambil, lalu mulai kerjakan.
            </p>

            <form wire:submit="startWork" class="space-y-3">
                <div>
                    <x-nawasara-ui::form.textarea
                        label="Tanggapan Awal"
                        wire:model="body"
                        :rows="3"
                        hint="Dibaca warga pelapor — sebutkan apa yang akan dilakukan." />
                    @error('body')
                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <x-nawasara-ui::button type="submit" color="primary">
                    Mulai Kerjakan
                </x-nawasara-ui::button>
            </form>

        {{-- ── Sedang dikerjakan → serahkan ke pemeriksa ── --}}
        @elseif ($status === $R::STATUS_IN_PROGRESS && $bisaTangani)

            {{-- ⚠️ Buntu yang DIAM adalah yang paling merugikan: tombol mati
                 tanpa sebab akan dilaporkan sebagai aplikasi rusak, dan yang
                 dicari adalah bugnya, bukan data keanggotaannya.

                 Enam dari tujuh OPD saat ini baru punya satu pengguna
                 terdaftar, dan larangan menunjuk diri sendiri bersifat
                 mutlak — jadi keadaan ini akan sering ditemui. --}}
            @if ($this->hasNoVerifier())
                <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 dark:border-amber-800 dark:bg-amber-900/30">
                    <p class="text-sm font-medium text-amber-900 dark:text-amber-200">
                        Belum ada pemeriksa yang dapat dipilih.
                    </p>
                    <p class="mt-1 text-sm text-amber-800 dark:text-amber-300">
                        Perangkat daerah Anda baru memiliki satu pengguna terdaftar,
                        dan pekerjaan tidak dapat diperiksa oleh orang yang mengerjakannya.
                    </p>
                    <p class="mt-2 text-xs text-amber-700 dark:text-amber-400">
                        Tambahkan anggota lewat menu <span class="font-medium">Registry Aset →
                        Keanggotaan OPD</span>, lalu buka kembali halaman ini.
                    </p>
                </div>
            @else
                <p class="mb-3 text-sm text-neutral-600 dark:text-neutral-300">
                    Pekerjaan selesai? Serahkan ke pemeriksa untuk disetujui.
                </p>

                <form wire:submit="submitForVerification" class="space-y-3">
                    <div>
                        <x-nawasara-ui::form.select
                            label="Pemeriksa"
                            wire:model="verifierId"
                            :options="$this->verifierOptions"
                            placeholder="— pilih pemeriksa —" />
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            Pilih atasan yang memeriksa pekerjaan ini. Anda tidak dapat
                            memilih diri sendiri.
                        </p>
                        @error('verifierId')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <x-nawasara-ui::form.textarea
                            label="Uraian Pekerjaan"
                            wire:model="body"
                            :rows="3"
                            hint="Apa yang sudah dikerjakan — dibaca pemeriksa dan warga." />
                        @error('body')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-nawasara-ui::button type="submit" color="primary">
                        Serahkan ke Pemeriksa
                    </x-nawasara-ui::button>
                </form>
            @endif

        {{-- ── Menunggu diperiksa ── --}}
        @elseif ($status === $R::STATUS_AWAITING_VERIFICATION)

            @if ($this->isVerifier() && $bisaPeriksa)
                <p class="mb-3 text-sm text-neutral-600 dark:text-neutral-300">
                    Anda ditunjuk memeriksa pekerjaan ini. Setujui bila sudah sesuai,
                    atau kembalikan disertai alasannya.
                </p>

                <div class="flex flex-wrap gap-2">
                    <x-nawasara-ui::button color="success" wire:click="approve">
                        Setujui — Laporan Selesai
                    </x-nawasara-ui::button>

                    <x-nawasara-ui::button
                        color="danger"
                        x-on:click="$dispatch('open-modal', { id: 'aspirations-reject-work' })">
                        Kembalikan
                    </x-nawasara-ui::button>
                </div>

                <x-nawasara-ui::modal id="aspirations-reject-work" title="Kembalikan Pekerjaan">
                    <p class="mb-3 text-sm text-neutral-600 dark:text-neutral-300">
                        Laporan kembali berstatus sedang dikerjakan. Alasannya dibaca
                        petugas yang mengerjakan.
                    </p>

                    <x-nawasara-ui::form.textarea
                        label="Alasan Pengembalian"
                        wire:model="rejectReason"
                        :rows="3"
                        hint="Wajib diisi — sebutkan apa yang belum sesuai." />

                    @error('rejectReason')
                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror

                    <x-slot name="footer">
                        {{-- wire:click, bukan submit: footer modal dirender di
                             LUAR <form>, jadi wire:submit tidak pernah menyala. --}}
                        <x-nawasara-ui::button color="neutral"
                            x-on:click="$dispatch('close-modal', 'aspirations-reject-work')">
                            Batal
                        </x-nawasara-ui::button>

                        <x-nawasara-ui::button color="danger" wire:click="rejectWork">
                            Kembalikan
                        </x-nawasara-ui::button>
                    </x-slot>
                </x-nawasara-ui::modal>
            @else
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800/60">
                    <p class="text-sm text-neutral-700 dark:text-neutral-200">
                        Menunggu pemeriksaan
                        @if ($report->verifier)
                            oleh <span class="font-medium">{{ $report->verifier->name }}</span>
                        @endif.
                    </p>
                    @if ($report->verification_due_at)
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            Batas pemeriksaan {{ $report->verification_due_at->translatedFormat('d F Y, H:i') }}.
                        </p>
                    @endif
                </div>
            @endif

        {{-- ── Sudah tuntas ── --}}
        @elseif (in_array($status, [$R::STATUS_RESOLVED, $R::STATUS_REJECTED], true))
            <p class="text-sm text-neutral-600 dark:text-neutral-300">
                Laporan sudah {{ $status === $R::STATUS_RESOLVED ? 'selesai' : 'ditolak' }}.
                Tidak ada tindakan yang tersisa.
            </p>

        @else
            <p class="text-sm text-neutral-500 dark:text-neutral-400">
                Belum ada tindakan yang dapat Anda lakukan pada tahap ini.
            </p>
        @endif
    </x-nawasara-ui::page.card>
</div>
