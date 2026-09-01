<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[
                ['label' => 'Lapor Bunda', 'url' => '#'],
                ['label' => 'Laporan Masuk', 'url' => route('nawasara-aspirations.reports')],
                ['label' => $report->code],
            ]" />
    </x-slot>

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

        $statusColor = [
            $R::STATUS_SUBMITTED => 'neutral',
            $R::STATUS_DISPATCHED => 'info',
            $R::STATUS_IN_PROGRESS => 'warning',
            $R::STATUS_AWAITING_VERIFICATION => 'warning',
            $R::STATUS_RESOLVED => 'success',
            $R::STATUS_REJECTED => 'danger',
        ];

        $field = fn (?string $v) => $v ?: '—';
    @endphp

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header
            :title="$report->code"
            :description="$report->category->name ?? 'Tanpa kategori'">

            <x-nawasara-ui::badge :color="$statusColor[$report->status] ?? 'neutral'">
                {{ $statusLabel[$report->status] ?? $report->status }}
            </x-nawasara-ui::badge>
        </x-nawasara-ui::page-header>

        <div class="space-y-4">
            <x-nawasara-ui::page.card>
                <h3 class="mb-4 text-sm font-semibold text-neutral-800 dark:text-neutral-100">
                    Isi Laporan
                </h3>

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
                        <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">OPD Tujuan</dt>
                        <dd class="mt-1 text-neutral-800 dark:text-neutral-100">{{ $field($report->opd->name ?? null) }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Dilaporkan</dt>
                        <dd class="mt-1 text-neutral-800 dark:text-neutral-100">
                            {{ $report->created_at?->translatedFormat('d F Y, H:i') ?? '—' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Batas Penyelesaian</dt>
                        <dd class="mt-1 text-neutral-800 dark:text-neutral-100">
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
                            <dd class="mt-1 text-neutral-800 dark:text-neutral-100">{{ $report->verifier->name }}</dd>
                        </div>
                    @endif

                    <div class="sm:col-span-2 lg:col-span-3">
                        <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Lokasi</dt>
                        <dd class="mt-1 text-neutral-800 dark:text-neutral-100">
                            {{ $field($report->full_address) }}
                            @if ($report->village || $report->district)
                                <span class="block text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ trim(($report->village ?? '').' · '.($report->district ?? ''), ' ·') }}
                                </span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-nawasara-ui::page.card>

            <livewire:nawasara-aspirations.report.section.handling
                :report="$report"
                :key="'handling-'.$report->id.'-'.$report->status" />
        </div>
    </x-nawasara-ui::page.container>
</div>
