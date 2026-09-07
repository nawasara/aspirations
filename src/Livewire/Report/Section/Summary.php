<?php

declare(strict_types=1);

namespace Nawasara\Aspirations\Livewire\Report\Section;

use Livewire\Attributes\On;
use Livewire\Component;
use Nawasara\Aspirations\Models\Report;

/**
 * Ringkasan satu laporan — petak bento di kepala halaman.
 *
 * Component, bukan partial blade, karena isinya BERUBAH saat panel penanganan
 * di bawahnya menyimpan. Sebagai partial ia akan menampilkan status lama sampai
 * halaman dimuat ulang, dan petugas mengira aksinya gagal.
 *
 * Yang ditonjolkan di petak besar adalah **status + sisa waktu**, bukan judul
 * laporan. Judulnya sudah ada di badan laporan tepat di bawah; yang tidak
 * terjawab tanpa menghitung sendiri adalah "masih ada waktu berapa lama".
 * Itulah pertanyaan yang membuat petugas membuka halaman ini.
 */
class Summary extends Component
{
    public Report $report;

    public function mount(Report $report): void
    {
        $this->report = $report;
    }

    /**
     * Ambil ULANG setelah panel penanganan mengubah status.
     *
     * ⚠️ `refresh()` saja tidak cukup — Livewire menghidupkan model ini dari
     * snapshot permintaan sebelumnya, dan snapshot itu memuat status lama.
     */
    #[On('report-updated')]
    public function refreshReport(): void
    {
        $this->report = Report::withoutGlobalScopes()
            ->with(['category', 'opd', 'responder', 'verifier', 'verifiedBy', 'districtRef', 'citizen'])
            ->findOrFail($this->report->getKey());
    }

    /**
     * Warna hero mengikuti KEADAAN, bukan sekadar hiasan.
     *
     * Merah dipakai untuk telat — bukan untuk "ditolak". Laporan yang ditolak
     * sudah selesai urusannya dan tidak menuntut apa pun dari petugas; yang
     * telat menuntut tindakan hari ini. Memberi keduanya warna yang sama
     * membuat warna merah berhenti berarti "kerjakan sekarang".
     */
    public function heroGradient(): string
    {
        if ($this->isOverdue()) {
            return 'from-rose-500 to-rose-700 dark:from-rose-700 dark:to-rose-900';
        }

        return match ($this->report->status) {
            Report::STATUS_RESOLVED
                => 'from-emerald-500 to-emerald-700 dark:from-emerald-700 dark:to-emerald-900',
            Report::STATUS_REJECTED
                => 'from-slate-500 to-slate-700 dark:from-slate-700 dark:to-slate-900',
            Report::STATUS_AWAITING_VERIFICATION
                => 'from-violet-500 to-violet-700 dark:from-violet-700 dark:to-violet-900',
            Report::STATUS_IN_PROGRESS
                => 'from-amber-500 to-amber-600 dark:from-amber-700 dark:to-amber-900',
            Report::STATUS_DISPATCHED
                => 'from-sky-500 to-sky-700 dark:from-sky-700 dark:to-sky-900',
            default
                => 'from-slate-500 to-slate-700 dark:from-slate-700 dark:to-slate-900',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->report->status) {
            Report::STATUS_SUBMITTED => 'Masuk',
            Report::STATUS_DISPATCHED => 'Diteruskan',
            Report::STATUS_IN_PROGRESS => 'Dikerjakan',
            Report::STATUS_AWAITING_VERIFICATION => 'Menunggu Pemeriksaan',
            Report::STATUS_RESOLVED => 'Selesai',
            Report::STATUS_REJECTED => 'Ditolak',
            default => $this->report->status,
        };
    }

    /** Dipakai badge di luar hero, yang tidak berlatar gelap. */
    public function statusColor(): string
    {
        return match ($this->report->status) {
            Report::STATUS_RESOLVED => 'success',
            Report::STATUS_REJECTED => 'danger',
            Report::STATUS_IN_PROGRESS, Report::STATUS_AWAITING_VERIFICATION => 'warning',
            Report::STATUS_DISPATCHED => 'info',
            default => 'neutral',
        };
    }

    public function isOverdue(): bool
    {
        return $this->report->isResolutionOverdue();
    }

    /** Laporan yang tidak lagi menunggu tindakan siapa pun. */
    public function isClosed(): bool
    {
        return in_array($this->report->status, [
            Report::STATUS_RESOLVED,
            Report::STATUS_REJECTED,
        ], true);
    }

    /**
     * Kalimat sisa waktu — inti petak besar.
     *
     * Dikembalikan sebagai kalimat jadi, bukan angka jam, karena "3 hari lagi"
     * dan "telat 2 hari" dibaca seketika sementara "72" menuntut pembacanya
     * menghitung sendiri.
     */
    public function slaHeadline(): string
    {
        if ($this->isClosed()) {
            return $this->report->status === Report::STATUS_RESOLVED ? 'Selesai' : 'Ditutup';
        }

        if ($this->report->sla_due_at === null) {
            return 'Tanpa batas waktu';
        }

        $sisa = $this->report->sla_due_at->diffForHumans(null, true);

        return $this->isOverdue() ? 'Telat '.$sisa : $sisa.' lagi';
    }

    /** Baris kecil di bawah kalimat sisa waktu. */
    public function slaCaption(): ?string
    {
        if ($this->isClosed()) {
            $pada = $this->report->resolvedAt();

            return $pada ? 'pada '.$pada->translatedFormat('d F Y') : null;
        }

        if ($this->report->sla_due_at === null) {
            // Kategori tanpa SLA bukan kelalaian — sebagian memang tidak
            // menjanjikan tenggat. Menyebut alasannya mencegah petugas
            // mengira datanya rusak.
            return 'kategori ini tidak menjanjikan tenggat';
        }

        return 'batas '.$this->report->sla_due_at->translatedFormat('d F Y, H:i');
    }

    /**
     * Kemajuan waktu SLA dalam persen, untuk bilah di hero.
     *
     * Dihitung dari waktu masuk sampai tenggat. Dibatasi 100 supaya bilah yang
     * sudah lewat tidak meluber keluar petaknya — angka telatnya tetap terbaca
     * di kalimat, jadi tidak ada yang hilang.
     *
     * null berarti tidak dapat digambar: tanpa tenggat, persentase tidak punya
     * penyebut dan hanya akan menyesatkan.
     */
    public function slaPercent(): ?int
    {
        if ($this->report->sla_due_at === null || $this->report->received_at === null) {
            return null;
        }

        $total = $this->report->received_at->diffInSeconds($this->report->sla_due_at, false);

        if ($total <= 0) {
            return 100;
        }

        $lewat = $this->report->received_at->diffInSeconds(now(), false);

        return (int) max(0, min(100, round($lewat / $total * 100)));
    }

    /**
     * Nama pelapor untuk ditampilkan, ATAU null bila tidak boleh disebut.
     *
     * Tiga keadaan yang berbeda, dan ketiganya perlu dibedakan di layar:
     *
     *  - laporan anonim                → null, ditampilkan sebagai "Anonim"
     *  - profil warga belum ada        → null, ditampilkan sebagai "Warga Ponorogo"
     *  - nama diketahui                → namanya
     *
     * Profil warga dibuat saat login pertama (nawasara/citizen), jadi keadaan
     * kedua wajar terjadi — bukan tanda data rusak.
     */
    public function reporterName(): ?string
    {
        return $this->report->reporter_name;
    }

    /**
     * Bolehkah pembaca melihat nama di balik laporan ANONIM?
     *
     * Hanya Inspektorat, lewat `aspirations.report.reveal-identity` (#10).
     * Halaman ini TIDAK membuka namanya sendiri — ia hanya memberi tahu bahwa
     * identitasnya dapat dibuka, dan pembukaannya sendiri harus tercatat.
     * Membuka diam-diam di sini akan melewati pencatatan itu.
     */
    public function mayRevealIdentity(): bool
    {
        return $this->report->is_anonymous
            && auth()->user()?->can('aspirations.report.reveal-identity') === true;
    }

    /** Umur laporan — dipakai petak pendamping. */
    public function age(): string
    {
        return $this->report->received_at?->diffForHumans(null, true) ?? '—';
    }

    /**
     * Berapa langkah penanganan yang sudah tercatat.
     *
     * Catatan internal ikut dihitung: bagi petugas, catatan "menunggu anggaran"
     * adalah penanganan yang nyata, sekalipun warga tidak melihatnya.
     */
    public function timelineCount(): int
    {
        return $this->report->responses()->count();
    }

    public function photoCount(): int
    {
        return $this->report->attachments()->count();
    }

    public function render()
    {
        return view('nawasara-aspirations::livewire.pages.report.section.summary');
    }
}
