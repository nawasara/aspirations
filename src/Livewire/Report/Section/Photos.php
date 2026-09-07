<?php

declare(strict_types=1);

namespace Nawasara\Aspirations\Livewire\Report\Section;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Nawasara\Aspirations\Models\Attachment;
use Nawasara\Aspirations\Models\Report;

/**
 * Foto laporan & foto bukti tindak lanjut.
 *
 * Ini bagian yang paling lama hilang: 27 foto sudah ada di produksi sejak
 * aplikasi warga rilis, dan tidak satu pun pernah tampil di panel staf.
 * Petugas menilai laporan hanya dari teksnya — padahal justru fotonya yang
 * menunjukkan apakah jalan berlubang itu selebar telapak atau selebar mobil.
 *
 * Dipisah menjadi component sendiri KARENA URL-nya presigned dan berumur
 * pendek: memuatnya di Detail berarti setiap aksi penanganan (yang memuat
 * ulang laporan) ikut menandatangani ulang seluruh foto. Di sini ia hanya
 * dihitung sekali, dan panel penanganan tidak menyentuhnya.
 */
class Photos extends Component
{
    public Report $report;

    public function mount(Report $report): void
    {
        $this->report = $report;
    }

    /**
     * Foto warga dan foto bukti, terpisah.
     *
     * Dicampur, keduanya terbaca sama — padahal artinya berlawanan: yang satu
     * keluhan, yang satu bukti keluhan itu sudah ditangani.
     *
     * URL ditandatangani di sini, sekali per foto. `temporaryUrl()`
     * mengembalikan null kalau berkasnya hilang atau disk-nya belum terpasang;
     * yang null tetap ditampilkan sebagai kartu rusak, bukan disembunyikan —
     * foto yang lenyap adalah hal yang perlu diketahui staf, bukan
     * disembunyikan darinya.
     *
     * @return array<string, array<int, array{url: ?string, at: ?string, suspect: bool, source: ?string}>>
     */
    #[Computed]
    public function groups(): array
    {
        $out = [Attachment::KIND_REPORT => [], Attachment::KIND_EVIDENCE => []];

        $rows = $this->report->attachments()
            ->orderBy('created_at')
            ->get();

        foreach ($rows as $row) {
            $kind = $row->kind === Attachment::KIND_EVIDENCE
                ? Attachment::KIND_EVIDENCE
                : Attachment::KIND_REPORT;

            // setRelation supaya isSuspect() tidak menembak query laporan lagi
            // untuk tiap foto — ia membandingkan captured_at dengan received_at.
            $row->setRelation('report', $this->report);

            $out[$kind][] = [
                'url' => $row->temporaryUrl(),
                'at' => $row->captured_at?->translatedFormat('d M Y, H:i'),
                'suspect' => $row->isSuspect(),
                'source' => $row->source,
            ];
        }

        return $out;
    }

    public function render()
    {
        return view('nawasara-aspirations::livewire.pages.report.section.photos');
    }
}
