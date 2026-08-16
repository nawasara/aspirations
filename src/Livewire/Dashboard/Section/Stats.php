<?php

namespace Nawasara\Aspirations\Livewire\Dashboard\Section;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Nawasara\Aspirations\Models\Report;

/**
 * Angka ringkas Lapor Bunda.
 *
 * Semua hitungan dilakukan di basis data, bukan dengan mengambil baris lalu
 * menghitungnya di PHP. Bedanya tidak terasa saat masih puluhan laporan, tetapi
 * halaman ini akan tetap dibuka saat sudah puluhan ribu.
 *
 * ⚠️ Report memakai ScopedToOpd, jadi seluruh angka di sini OTOMATIS ter-scope
 * ke OPD si pembaca. Admin kabupaten dan Inspektorat melihat lintas-OPD karena
 * perannya istimewa; operator OPD hanya melihat miliknya sendiri. Itu memang
 * yang diinginkan — jangan menambahkan withoutGlobalScopes() di sini.
 */
class Stats extends Component
{
    /** Rentang hari yang dihitung. 0 = seluruhnya. */
    public int $days = 30;

    public function setRange(int $days): void
    {
        $this->days = $days;

        // Computed di-cache per permintaan; membersihkannya memaksa hitung ulang.
        unset($this->summary, $this->byStatus, $this->byOpd);
    }

    /** Kueri dasar yang sudah menghormati rentang waktu terpilih. */
    protected function base()
    {
        return Report::query()
            ->when($this->days > 0, fn ($q) => $q->where('created_at', '>=', now()->subDays($this->days)));
    }

    #[Computed]
    public function summary(): array
    {
        $total = (clone $this->base())->count();
        $open = (clone $this->base())->whereIn('status', Report::OPEN_STATUSES)->count();
        $resolved = (clone $this->base())->where('status', Report::STATUS_RESOLVED)->count();

        // Lewat batas = masih berjalan DAN tenggatnya sudah lewat. Laporan yang
        // sudah selesai tidak dihitung terlambat meski selesainya lewat tenggat —
        // yang diukur di sini adalah tunggakan yang masih harus dikerjakan.
        $overdue = (clone $this->base())
            ->whereIn('status', Report::OPEN_STATUSES)
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->count();

        $awaitingVerification = (clone $this->base())
            ->where('status', Report::STATUS_AWAITING_VERIFICATION)
            ->count();

        // Rata-rata bintang. Laporan tanpa penilaian tidak ikut dihitung —
        // memasukkannya sebagai nol akan menyeret rata-rata ke bawah dan
        // membuat kinerja tampak lebih buruk daripada yang sebenarnya.
        $rating = (clone $this->base())->whereNotNull('rating')->avg('rating');

        return [
            'total' => $total,
            'open' => $open,
            'resolved' => $resolved,
            'overdue' => $overdue,
            'awaiting_verification' => $awaitingVerification,
            'rating' => $rating ? round((float) $rating, 1) : null,
            // Kepatuhan hanya bermakna bila ada yang sudah tuntas.
            'compliance' => $total > 0 ? round(($total - $overdue) / $total * 100) : null,
        ];
    }

    #[Computed]
    public function byStatus(): array
    {
        return (clone $this->base())
            ->select('status', DB::raw('count(*) as jumlah'))
            ->groupBy('status')
            ->pluck('jumlah', 'status')
            ->all();
    }

    /**
     * Lima OPD dengan laporan berjalan terbanyak. Yang menarik bagi pemantau
     * bukan siapa yang paling banyak menerima, melainkan siapa yang paling
     * banyak menunggak — jadi diurutkan menurut yang lewat batas.
     */
    #[Computed]
    public function byOpd(): array
    {
        $openList = "'".implode("','", Report::OPEN_STATUSES)."'";

        $rows = (clone $this->base())
            ->whereNotNull('opd_id')
            ->select(
                'opd_id',
                DB::raw('count(*) as jumlah'),
                DB::raw("sum(case when status in ({$openList}) and sla_due_at < now() then 1 else 0 end) as terlambat")
            )
            ->groupBy('opd_id')
            ->orderByDesc('terlambat')
            ->orderByDesc('jumlah')
            ->limit(5)
            ->get();

        // Nama OPD diambil terpisah, bukan lewat with(). Baris di atas hasil
        // agregat — bukan model Report utuh — sehingga eager loading relasi
        // padanya tidak dapat diandalkan.
        $names = \Nawasara\Registry\Models\Opd::whereIn('id', $rows->pluck('opd_id'))
            ->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            'name' => $names[$row->opd_id] ?? 'OPD tidak dikenal',
            'jumlah' => (int) $row->jumlah,
            'terlambat' => (int) $row->terlambat,
        ])->all();
    }

    public function render()
    {
        return view('nawasara-aspirations::livewire.pages.dashboard.section.stats');
    }
}
