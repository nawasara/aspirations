<?php

namespace Nawasara\Aspirations\Livewire\Report;

use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\Aspirations\Models\Category;
use Nawasara\Aspirations\Models\Report;

/**
 * Daftar laporan warga — HANYA UNTUK DILIHAT.
 *
 * Tidak ada tombol disposisi, tanggapan, maupun verifikasi di sini. Penanganan
 * laporan ada di panel Next.js lewat `api.staff`, supaya aturan bisnisnya tetap
 * di satu tempat: setiap perubahan status wajib melewati ReportWorkflow, dan
 * dua panel yang sama-sama mengubah status berarti dua tempat yang harus sama
 * benarnya selamanya.
 *
 * ⚠️ Report memakai ScopedToOpd. Daftar ini otomatis ter-scope ke OPD pembaca;
 * hanya peran istimewa (developer, inspektorat, pengawas-sla, admin-kabupaten)
 * yang melihat lintas-OPD.
 */
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $category = '';

    /** Hanya yang sudah lewat batas waktu. */
    #[Url]
    public bool $overdue = false;

    public function mount(): void
    {
        $this->authorize('aspirations.report.view');
    }

    public function updated(string $field): void
    {
        // Penyaring berubah → kembali ke halaman pertama. Tanpa ini, pengguna
        // yang sedang di halaman 5 lalu menyaring bisa mendapat halaman kosong
        // dan menyimpulkan tidak ada datanya.
        if ($field !== 'page') {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'category', 'overdue']);
        $this->resetPage();
    }

    public function render()
    {
        $reports = Report::query()
            ->with(['category:id,name,icon_name', 'opd:id,name'])
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($x) => $x->where('code', 'like', $term)
                    ->orWhere('title', 'like', $term));
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->category !== '', fn ($q) => $q->where('category_id', $this->category))
            ->when($this->overdue, fn ($q) => $q
                ->whereIn('status', Report::OPEN_STATUSES)
                ->whereNotNull('sla_due_at')
                ->where('sla_due_at', '<', now()))
            ->latest('created_at')
            ->paginate(20);

        return view('nawasara-aspirations::livewire.pages.report.index', [
            'reports' => $reports,
            'categories' => Category::orderBy('sort_order')->get(['id', 'name']),
        ])->layout('nawasara-ui::components.layouts.app');
    }
}
