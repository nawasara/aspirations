<div>
    @php
        $persen = $this->slaPercent();
        $telat = $this->isOverdue();
        $ditutup = $this->isClosed();
        $field = fn (?string $v) => $v ?: '—';
    @endphp

    {{-- ── Bento: sisa waktu di petak besar, penghitung di kanan ──
         Yang dicari pertama saat membuka laporan bukan judulnya — itu sudah
         terbaca di badan laporan tepat di bawah — melainkan "masih ada waktu
         berapa lama". Karena itu ia yang mendapat petak terbesar dan kontras
         tertinggi; sisanya menjelaskan. --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

        {{-- Petak utama --}}
        <div class="relative overflow-hidden rounded-xl bg-linear-to-br {{ $this->heroGradient() }} p-6 text-white shadow-sm lg:col-span-2">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-medium uppercase tracking-wider text-white/70">
                        {{ $ditutup ? 'Perkara Selesai' : ($telat ? 'Melewati Batas Waktu' : 'Sisa Waktu') }}
                    </p>

                    <p class="mt-2 text-3xl font-bold sm:text-4xl">
                        {{ $this->slaHeadline() }}
                    </p>

                    @if ($this->slaCaption())
                        <p class="mt-2 text-xs text-white/80">
                            {{ $this->slaCaption() }}
                        </p>
                    @endif
                </div>

                <span class="shrink-0 rounded-full bg-white/20 px-3 py-1 text-xs font-medium backdrop-blur">
                    {{ $this->statusLabel() }}
                </span>
            </div>

            {{-- Bilah hanya digambar bila tenggatnya diketahui: tanpa penyebut,
                 persentase hanya menyesatkan. --}}
            @if ($persen !== null && ! $ditutup)
                <div class="mt-5">
                    <div class="flex items-center justify-between text-xs text-white/80">
                        <span>Waktu terpakai {{ $persen }}%</span>
                        <span>{{ $this->age() }} sejak dilaporkan</span>
                    </div>

                    <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-white/25">
                        <div class="h-full rounded-full bg-white transition-all"
                            style="width: {{ $persen }}%"></div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Petak pendamping — penghitung yang menjawab "ada isinya atau tidak"
             sebelum petugas menggulir ke panelnya. --}}
        <div class="grid grid-cols-3 gap-4 lg:grid-cols-1">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                <p class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
                    Foto
                </p>
                <p class="mt-1 text-xl font-semibold tabular-nums text-neutral-800 dark:text-neutral-100">
                    {{ $this->photoCount() }}
                </p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                <p class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
                    Riwayat
                </p>
                <p class="mt-1 text-xl font-semibold tabular-nums text-neutral-800 dark:text-neutral-100">
                    {{ $this->timelineCount() }}
                </p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                <p class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
                    Dukungan
                </p>
                <p class="mt-1 text-xl font-semibold tabular-nums {{ ($report->support_count ?? 0) >= 5 ? 'text-amber-700 dark:text-amber-400' : 'text-neutral-800 dark:text-neutral-100' }}">
                    {{ number_format((int) ($report->support_count ?? 0), 0, ',', '.') }}
                </p>
            </div>
        </div>
    </div>

    {{-- ── Isi laporan + rinciannya ── --}}
    <x-nawasara-ui::page.card class="mt-4">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-100">
                Isi Laporan
            </h3>

            <div class="flex flex-wrap items-center gap-2">
                @if ($report->category)
                    <x-nawasara-ui::badge color="info">{{ $report->category->name }}</x-nawasara-ui::badge>
                @endif

                @if ($report->is_anonymous)
                    {{-- Ditandai jelas: petugas tidak boleh mengira nama pelapor
                         hilang karena datanya rusak. Yang disembunyikan hanya
                         namanya dari staf — sistem tetap tahu siapa. --}}
                    <x-nawasara-ui::badge color="neutral">Anonim</x-nawasara-ui::badge>
                @endif

                <x-nawasara-ui::badge :color="$this->statusColor()">
                    {{ $this->statusLabel() }}
                </x-nawasara-ui::badge>
            </div>
        </div>

        @if ($report->title)
            <p class="mb-2 font-medium text-neutral-900 dark:text-neutral-50">
                {{ $report->title }}
            </p>
        @endif

        <p class="whitespace-pre-line text-sm text-neutral-800 dark:text-neutral-100">
            {{ $report->description }}
        </p>

        <dl class="mt-4 grid grid-cols-1 gap-4 border-t border-gray-200 pt-4 text-sm sm:grid-cols-2 lg:grid-cols-3 dark:border-neutral-700">
            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Kode Laporan</dt>
                <dd class="mt-1 font-medium tabular-nums text-neutral-800 dark:text-neutral-100">{{ $report->code }}</dd>
            </div>

            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Pelapor</dt>
                <dd class="mt-1 text-neutral-800 dark:text-neutral-100">
                    @if ($report->is_anonymous)
                        <span class="text-neutral-500 dark:text-neutral-400">Anonim</span>

                        @if ($this->mayRevealIdentity())
                            {{-- Namanya TIDAK dibuka di sini. Pembukaan
                                 identitas wajib tercatat (#10), dan menampilkan
                                 begitu saja akan melewati pencatatan itu.
                                 Yang disebut hanya bahwa haknya ada. --}}
                            <span class="block text-xs text-amber-700 dark:text-amber-400">
                                Identitas dapat dibuka — setiap pembukaan tercatat.
                            </span>
                        @endif
                    @elseif ($this->reporterName())
                        {{ $this->reporterName() }}
                    @else
                        {{-- Profil warga dibuat saat login pertama, jadi laporan
                             dari warga yang profilnya belum ada itu wajar —
                             bukan tanda data rusak. Sebutan netral lebih baik
                             daripada nama tebakan. --}}
                        <span class="text-neutral-500 dark:text-neutral-400">Warga Ponorogo</span>
                    @endif
                </dd>
            </div>

            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">OPD Tujuan</dt>
                <dd class="mt-1 text-neutral-800 dark:text-neutral-100">{{ $field($report->opd->name ?? null) }}</dd>
            </div>

            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Dilaporkan</dt>
                <dd class="mt-1 text-neutral-800 dark:text-neutral-100">
                    {{ $report->received_at?->translatedFormat('d F Y, H:i') ?? '—' }}
                </dd>
            </div>

            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Batas Penyelesaian</dt>
                <dd class="mt-1 {{ $telat ? 'text-rose-600 dark:text-rose-400' : 'text-neutral-800 dark:text-neutral-100' }}">
                    {{ $report->sla_due_at?->translatedFormat('d F Y, H:i') ?? '—' }}
                </dd>
            </div>

            @if ($report->responder)
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Dikerjakan Oleh</dt>
                    <dd class="mt-1 text-neutral-800 dark:text-neutral-100">{{ $report->responder->name }}</dd>
                </div>
            @endif

            @if ($report->verifier)
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Pemeriksa</dt>
                    <dd class="mt-1 text-neutral-800 dark:text-neutral-100">
                        {{ $report->verifier->name }}

                        {{-- Yang MENYETUJUI bisa berbeda dari yang ditunjuk —
                             dan justru saat berbeda itulah yang perlu terlihat
                             saat audit. --}}
                        @if ($report->verifiedBy && $report->verifiedBy->id !== $report->verifier->id)
                            <span class="block text-xs text-neutral-500 dark:text-neutral-400">
                                disetujui oleh {{ $report->verifiedBy->name }}
                            </span>
                        @endif
                    </dd>
                </div>
            @endif

            <div class="sm:col-span-2 lg:col-span-3">
                <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Lokasi</dt>
                <dd class="mt-1 text-neutral-800 dark:text-neutral-100">
                    {{ $field($report->full_address) }}

                    @if ($report->village || $report->district_name)
                        <span class="block text-xs text-neutral-500 dark:text-neutral-400">
                            {{ trim(($report->village ?? '').' · '.($report->district_name ?? ''), ' ·') }}
                        </span>
                    @endif
                </dd>
            </div>
        </dl>
    </x-nawasara-ui::page.card>
</div>
