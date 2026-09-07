<?php

declare(strict_types=1);

namespace Nawasara\Aspirations\Livewire\Report\Section;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Nawasara\Aspirations\Models\Report;

/**
 * Riwayat penanganan satu laporan.
 *
 * Sumbernya tabel `responses`, yang memang sudah merekam SETIAP perubahan
 * status beserta tanggapannya — tidak perlu tabel riwayat terpisah seperti di
 * nawasara/hibah, karena di sini riwayat dan tanggapan adalah benda yang sama.
 *
 * Yang belum ada selama ini cuma tampilannya: 11 tanggapan tersimpan di
 * produksi tanpa satu pun halaman yang menampilkannya.
 */
class Timeline extends Component
{
    public Report $report;

    public function mount(Report $report): void
    {
        $this->report = $report;
    }

    /**
     * Panel penanganan menyiarkan ini tiap kali status berpindah.
     *
     * Tanpa ini linimasa tetap memperlihatkan keadaan sebelum aksi — petugas
     * menekan "Serahkan ke Pemeriksa", statusnya berubah di kartu atas, dan
     * riwayat di bawahnya belum menyebutnya.
     */
    #[On('report-updated')]
    public function refreshTimeline(): void
    {
        unset($this->entries);
    }

    /**
     * Seluruh tanggapan, termasuk yang internal.
     *
     * scopePublic() SENGAJA tidak dipakai di sini: penyaringan itu untuk warga.
     * Panel ini dibaca petugas, dan catatan internal ("menunggu anggaran
     * triwulan berikutnya") justru bagian yang paling menjelaskan kenapa
     * sebuah laporan berhenti bergerak.
     */
    #[Computed]
    public function entries()
    {
        return $this->report->responses()
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get();
    }

    public function render()
    {
        return view('nawasara-aspirations::livewire.pages.report.section.timeline');
    }
}
