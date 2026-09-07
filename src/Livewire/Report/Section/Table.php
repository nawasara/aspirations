<?php

namespace Nawasara\Aspirations\Livewire\Report\Section;

use Illuminate\Support\Facades\Response as ResponseFacade;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\Aspirations\Models\Category;
use Nawasara\Aspirations\Models\District;
use Nawasara\Aspirations\Models\Report;

/**
 * Tabel laporan warga — penyaring, pencarian, paginasi.
 *
 * Mengikuti bentuk baku halaman daftar (§1a AGENTS.md): satu `filter-panel`
 * berisi seluruh saringan, `search-input` di sebelahnya, chip saringan aktif di
 * bawahnya, dan aksi baris lewat dropdown tiga titik.
 *
 * Sebelumnya halaman ini memakai grid empat kolom buatan sendiri berisi
 * `form.select` mentah — berfungsi, tetapi terlihat berbeda dari setiap halaman
 * daftar lain di Nawasara, dan perbedaan itu hal pertama yang disadari staf.
 *
 * ⚠️ HANYA UNTUK DILIHAT. Tidak ada disposisi, tanggapan, maupun verifikasi di
 * sini — penanganan laporan ada di panel Next.js lewat `api.staff`, supaya
 * setiap perubahan status tetap melewati satu ReportWorkflow.
 */
class Table extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $categoryFilter = '';

    #[Url]
    public string $districtFilter = '';

    /** Hanya yang sudah lewat batas waktu. */
    #[Url]
    public string $overdueFilter = '';

    public function updated(string $field): void
    {
        // Penyaring berubah → kembali ke halaman pertama. Tanpa ini, pengguna
        // yang sedang di halaman 5 lalu menyaring bisa mendapat halaman kosong
        // dan menyimpulkan datanya tidak ada.
        if ($field !== 'page') {
            $this->resetPage();
        }
    }

    /** @return array<string, string> */
    #[Computed]
    public function statusOptions(): array
    {
        return [
            Report::STATUS_SUBMITTED => 'Masuk',
            Report::STATUS_DISPATCHED => 'Diteruskan',
            Report::STATUS_IN_PROGRESS => 'Dikerjakan',
            Report::STATUS_AWAITING_VERIFICATION => 'Menunggu Pemeriksaan',
            Report::STATUS_RESOLVED => 'Selesai',
            Report::STATUS_REJECTED => 'Ditolak',
        ];
    }

    /** @return array<int, string> */
    #[Computed]
    public function categoryOptions(): array
    {
        return Category::orderBy('sort_order')->pluck('name', 'id')->all();
    }

    /**
     * Hanya kecamatan yang BENAR-BENAR punya laporan.
     *
     * Menawarkan 21 kecamatan yang 19 di antaranya selalu kosong hanya membuang
     * waktu staf — dan membuat saringan ini terasa rusak.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function districtOptions(): array
    {
        $kode = Report::query()
            ->whereNotNull('district_code')
            ->distinct()
            ->pluck('district_code');

        if ($kode->isEmpty()) {
            return [];
        }

        return District::whereIn('code', $kode)
            ->orderBy('name')
            ->pluck('name', 'code')
            ->all();
    }

    /** @return array<string, string> */
    #[Computed]
    public function overdueOptions(): array
    {
        return ['1' => 'Lewat batas waktu'];
    }

    #[Computed]
    public function total(): int
    {
        return $this->query()->count();
    }

    protected function query()
    {
        return Report::query()
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($x) => $x->where('code', 'like', $term)
                    ->orWhere('title', 'like', $term));
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->categoryFilter !== '', fn ($q) => $q->where('category_id', $this->categoryFilter))
            ->when($this->districtFilter !== '', fn ($q) => $q->where('district_code', $this->districtFilter))

            // scopeOverdue(), BUKAN salinan aturannya. Versi sebelumnya menulis
            // ulang syarat SLA di sini, dan salinan seperti itu berbeda begitu
            // salah satunya diperbarui — yang berbeda adalah daftar laporan
            // yang dianggap telat.
            ->when($this->overdueFilter !== '', fn ($q) => $q->overdue());
    }

    /**
     * Unduh hasil saringan sebagai CSV.
     *
     * Izin `aspirations.report.export` sudah lama diseed tanpa satu pun kode
     * yang memakainya — staf yang diberi izin itu tetap tidak bisa mengunduh
     * apa pun. Ini yang memakainya.
     *
     * Yang diunduh adalah HASIL SARINGAN yang sedang terlihat, bukan seluruh
     * tabel: rekap yang diminta pimpinan hampir selalu "laporan telat di
     * kecamatan X", dan mengunduh semuanya lalu menyaring ulang di Excel
     * adalah pekerjaan yang sudah dilakukan halaman ini.
     *
     * Memakai streaming + chunk: sekali ekspor bisa mencakup puluhan ribu
     * baris, dan menyusunnya di memori lebih dulu membuat permintaannya mati
     * diam-diam justru saat datanya paling banyak.
     */
    public function export(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('aspirations.report.export');

        $kolom = ['Kode', 'Judul', 'Kategori', 'OPD', 'Kecamatan', 'Status',
            'Batas Waktu', 'Telat', 'Dikirim', 'Penilaian', 'Dukungan'];

        $label = $this->statusOptions;
        $query = $this->query()->with(['category:id,name', 'opd:id,name', 'districtRef:code,name']);
        $nama = 'laporan-warga-'.now()->format('Ymd-His').'.csv';

        return ResponseFacade::streamDownload(function () use ($kolom, $label, $query) {
            $out = fopen('php://output', 'w');

            // BOM UTF-8 — tanpa ini Excel di Windows salah menebak encoding,
            // dan nama kecamatan beraksen tampil rusak saat dibuka staf.
            fwrite($out, chr(0xEF).chr(0xBB).chr(0xBF));

            // $escape dinyatakan tegas: PHP 8.4 mendeprekasi nilai bawaannya,
            // dan '' adalah yang benar untuk CSV — bawaan lama ("\\")
            // memperlakukan backslash sebagai escape sehingga alamat seperti
            // "Jl. Raya \ Gg. Melati" tersimpan salah.
            fputcsv($out, $kolom, ',', '"', '');

            $query->orderBy('received_at')->chunk(500, function ($rows) use ($out, $label) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->code,
                        $r->title,
                        $r->category?->name,
                        $r->opd?->name,
                        $r->district_name,
                        $label[$r->status] ?? $r->status,
                        $r->sla_due_at?->format('Y-m-d H:i'),
                        $r->isResolutionOverdue() ? 'ya' : 'tidak',
                        $r->received_at?->format('Y-m-d H:i'),
                        $r->rating,
                        $r->support_count,
                    ], ',', '"', '');
                }
            });

            fclose($out);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render()
    {
        return view('nawasara-aspirations::livewire.pages.report.section.table', [
            'reports' => $this->query()
                // districtRef ikut di-load: kolom Kecamatan memakai accessor
                // district_name yang membacanya, dan tanpa ini tiap baris
                // menembak satu query sendiri.
                ->with(['category:id,name,icon_name', 'opd:id,name', 'districtRef:code,name'])
                ->latest('received_at')
                ->paginate(20),
        ]);
    }
}
