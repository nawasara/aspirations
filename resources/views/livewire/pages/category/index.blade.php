<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Ponorogo Hub', 'url' => '#'], ['label' => 'Kategori Laporan']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header
            title="Kategori Laporan"
            description="Kategori menentukan OPD tujuan, batas waktu yang dijanjikan, dan kewajiban foto bukti."
            :count="$categories->count()">
            @can('aspirations.category.manage')
                <x-nawasara-ui::button color="primary" wire:click="create">
                    Tambah Kategori
                </x-nawasara-ui::button>
            @endcan
        </x-nawasara-ui::page-header>

        @if ($categories->isEmpty())
            <x-nawasara-ui::empty-state
                icon="lucide-tags"
                title="Belum ada kategori"
                description="Tambahkan kategori agar warga dapat memilih jenis laporannya." />
        @else
            <x-nawasara-ui::table :headers="['Kategori', 'Kode', 'OPD Tujuan', 'Batas Waktu', 'Foto Bukti', 'Status', '']">
                @foreach ($categories as $category)
                    <tr wire:key="cat-{{ $category->id }}">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-neutral-800 dark:text-neutral-100">
                                {{ $category->name }}
                            </div>
                            @if ($category->hint)
                                <div class="mt-0.5 line-clamp-1 text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ $category->hint }}
                                </div>
                            @endif
                        </td>

                        <td class="px-6 py-4">
                            <code class="rounded bg-neutral-100 px-1.5 py-0.5 text-xs text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                                {{ $category->code }}
                            </code>
                        </td>

                        <td class="px-6 py-4 text-sm text-neutral-600 dark:text-neutral-300">
                            {{ $opdList[$category->opd_id] ?? '—' }}
                        </td>

                        <td class="px-6 py-4 text-sm text-neutral-700 dark:text-neutral-200">
                            {{ $category->sla_hours }} jam
                            <span class="block text-xs text-neutral-500 dark:text-neutral-400">
                                &asymp; {{ round($category->sla_hours / 24) }} hari
                                {{ $category->uses_working_days ? 'kerja' : 'kalender' }}
                            </span>
                        </td>

                        <td class="px-6 py-4 text-sm">
                            @if ($category->requires_evidence)
                                <x-nawasara-ui::badge color="info">wajib</x-nawasara-ui::badge>
                            @else
                                <span class="text-xs text-neutral-500 dark:text-neutral-400">tidak wajib</span>
                            @endif
                        </td>

                        <td class="px-6 py-4 text-sm">
                            @if ($category->is_active)
                                <x-nawasara-ui::badge color="success">aktif</x-nawasara-ui::badge>
                            @else
                                <x-nawasara-ui::badge color="neutral">nonaktif</x-nawasara-ui::badge>
                            @endif
                            @if ($category->is_sensitive)
                                <x-nawasara-ui::badge color="warning">sensitif</x-nawasara-ui::badge>
                            @endif
                        </td>

                        <td class="px-6 py-4 text-right">
                            @can('aspirations.category.manage')
                                <div class="flex justify-end gap-1">
                                    <x-nawasara-ui::icon-button
                                        icon="pencil" tooltip="Ubah" placement="left"
                                        wire:click="edit('{{ $category->id }}')" />
                                    <x-nawasara-ui::icon-button
                                        :icon="$category->is_active ? 'eye-off' : 'eye'"
                                        :tooltip="$category->is_active ? 'Nonaktifkan' : 'Aktifkan'"
                                        placement="left"
                                        wire:click="toggleActive('{{ $category->id }}')" />
                                </div>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-nawasara-ui::table>
        @endif

        {{-- Form tambah / ubah --}}
        @if ($showForm)
            <x-nawasara-ui::page.card class="mt-6">
                <h3 class="text-base font-semibold text-neutral-800 dark:text-neutral-100">
                    {{ $editingId ? 'Ubah Kategori' : 'Tambah Kategori' }}
                </h3>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><x-nawasara-ui::form.input label="Nama Kategori" wire:model="name" /></div>
                    <div>
                        <x-nawasara-ui::form.input label="Kode" wire:model="code" />
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            Huruf kecil dan tanda hubung. Dipakai aplikasi untuk memetakan ikon —
                            jangan diubah setelah dipakai.
                        </p>
                    </div>

                    <div class="sm:col-span-2">
                        <x-nawasara-ui::form.textarea label="Keterangan untuk Warga"
                            wire:model="hint" :rows="2"
                            hint="Contoh laporan yang termasuk kategori ini." />
                    </div>

                    <div>
                        <x-nawasara-ui::form.select label="OPD Tujuan" wire:model="opd_id"
                            placeholder="Belum ditentukan"
                            :options="$opdList->all()"
                            hint="Laporan langsung diteruskan ke OPD ini." />
                    </div>
                    <div>
                        <x-nawasara-ui::form.input type="number" label="Batas Waktu (Jam)"
                            wire:model="sla_hours" />
                        <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                            Inilah yang dijanjikan ke warga saat mengirim laporan.
                        </p>
                    </div>

                    <div>
                        <x-nawasara-ui::form.input label="Nama Ikon" wire:model="icon_name" />
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            Nama simbolik, dipetakan aplikasi ke ikonnya sendiri. Nama tak dikenal
                            jatuh ke ikon "lainnya" — tidak merusak tampilan.
                        </p>
                    </div>
                    <div>
                        <x-nawasara-ui::form.select label="Warna" wire:model="color"
                            :options="collect(['slate','stone','red','orange','amber','emerald','sky','blue','violet','rose'])
                                ->mapWithKeys(fn ($c) => [$c => ucfirst($c)])->all()" />
                    </div>
                    <div><x-nawasara-ui::form.input type="number" label="Urutan" wire:model="sort_order" /></div>
                </div>

                <div class="mt-4 flex flex-col gap-2">
                    <x-nawasara-ui::form.checkbox wire:model="requires_evidence"
                        label="Wajib foto bukti saat OPD menyelesaikan" />
                    <x-nawasara-ui::form.checkbox wire:model="uses_working_days"
                        label="Hitung batas waktu dengan hari kerja" />
                    <x-nawasara-ui::form.checkbox wire:model="is_sensitive"
                        label="Kategori sensitif" />
                    <x-nawasara-ui::form.checkbox wire:model="is_active"
                        label="Aktif — tampil di aplikasi warga" />
                </div>

                <div class="mt-6 flex gap-2">
                    <x-nawasara-ui::button color="primary" wire:click="save">Simpan</x-nawasara-ui::button>
                    <x-nawasara-ui::button color="neutral" wire:click="$set('showForm', false)">
                        Batal
                    </x-nawasara-ui::button>
                </div>
            </x-nawasara-ui::page.card>
        @endif
    </x-nawasara-ui::page.container>
</div>
