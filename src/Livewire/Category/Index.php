<?php

namespace Nawasara\Aspirations\Livewire\Category;

use Livewire\Component;
use Nawasara\Aspirations\Models\Category;
use Nawasara\Registry\Models\Opd;

/**
 * Kelola kategori laporan.
 *
 * Kategori bukan sekadar label: ia menentukan OPD tujuan disposisi, batas waktu
 * yang dijanjikan ke warga, dan apakah foto bukti wajib dilampirkan. Karena itu
 * halaman ini termasuk yang paling sering disentuh setelah rapat OPD.
 */
class Index extends Component
{
    public bool $showForm = false;

    public ?string $editingId = null;

    // Semua nullable / bertipe longgar mengikuti kolomnya di basis data.
    // Properti bertipe ketat akan melempar TypeError saat fill() menemui null.
    public string $name = '';

    public string $code = '';

    public ?string $hint = null;

    // icon_name dan color TIDAK nullable di basis data (color bahkan berbawaan
    // 'slate'), jadi keduanya string dengan nilai awal yang masuk akal. Dibiarkan
    // null, kategori baru tanpa ikon gagal disimpan dengan galat constraint yang
    // tidak menjelaskan apa pun kepada admin.
    public string $icon_name = 'circle-dot';

    public string $color = 'slate';

    public ?int $opd_id = null;

    public int $sla_hours = 336;

    public int $sort_order = 0;

    public bool $uses_working_days = false;

    public bool $is_active = true;

    public bool $is_sensitive = false;

    public bool $requires_evidence = true;

    public function mount(): void
    {
        $this->authorize('aspirations.category.view');
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'hint' => ['nullable', 'string', 'max:500'],
            'icon_name' => ['required', 'string', 'max:64'],
            'color' => ['required', 'string', 'max:32'],
            'opd_id' => ['nullable', 'integer', 'exists:nawasara_registry_opd,id'],
            'sla_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'uses_working_days' => ['boolean'],
            'is_active' => ['boolean'],
            'is_sensitive' => ['boolean'],
            'requires_evidence' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'code.regex' => 'Kode hanya boleh huruf kecil, angka, dan tanda hubung.',
            'sla_hours.max' => 'Batas waktu tidak boleh lebih dari setahun.',
        ];
    }

    public function create(): void
    {
        $this->authorize('aspirations.category.manage');
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(string $id): void
    {
        $this->authorize('aspirations.category.manage');

        $category = Category::findOrFail($id);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->code = $category->code;
        $this->hint = $category->hint;
        // Baris lama dari seed bisa saja kosong meski kolomnya NOT NULL —
        // string kosong lolos constraint tetapi melanggar tipe properti.
        $this->icon_name = $category->icon_name ?: 'circle-dot';
        $this->color = $category->color ?: 'slate';
        $this->opd_id = $category->opd_id;
        $this->sla_hours = $category->sla_hours;
        $this->sort_order = $category->sort_order;
        $this->uses_working_days = $category->uses_working_days;
        $this->is_active = $category->is_active;
        $this->is_sensitive = $category->is_sensitive;
        $this->requires_evidence = $category->requires_evidence;

        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('aspirations.category.manage');

        $validated = $this->validate();

        // Kode dipakai aplikasi untuk memetakan ikon, dan harus tetap unik.
        $duplicate = Category::where('code', $this->code)
            ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
            ->exists();

        if ($duplicate) {
            $this->addError('code', 'Kode ini sudah dipakai kategori lain.');

            return;
        }

        if ($this->editingId) {
            Category::findOrFail($this->editingId)->update($validated);
            $pesan = 'Kategori diperbarui.';
        } else {
            Category::create($validated);
            $pesan = 'Kategori ditambahkan.';
        }

        $this->showForm = false;
        $this->resetForm();

        $this->dispatch('toast', type: 'success', message: $pesan);
    }

    /**
     * Kategori TIDAK dihapus, hanya dinonaktifkan.
     *
     * Laporan yang sudah ada menunjuk kategorinya, dan menghapus baris itu akan
     * membuat laporan lama kehilangan asal-usulnya — termasuk laporan yang sudah
     * selesai dan menjadi catatan resmi.
     */
    public function toggleActive(string $id): void
    {
        $this->authorize('aspirations.category.manage');

        $category = Category::findOrFail($id);
        $category->update(['is_active' => ! $category->is_active]);

        $this->dispatch('toast', type: 'success',
            message: $category->is_active ? 'Kategori diaktifkan.' : 'Kategori dinonaktifkan.');
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->code = '';
        $this->hint = null;
        $this->icon_name = 'circle-dot';
        $this->color = 'slate';
        $this->opd_id = null;
        $this->sla_hours = 336;
        $this->sort_order = 0;
        $this->uses_working_days = false;
        $this->is_active = true;
        $this->is_sensitive = false;
        $this->requires_evidence = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('nawasara-aspirations::livewire.pages.category.index', [
            'categories' => Category::orderBy('sort_order')->orderBy('name')->get(),
            'opdList' => Opd::orderBy('name')->pluck('name', 'id'),
        ])->layout('nawasara-ui::components.layouts.app');
    }
}
