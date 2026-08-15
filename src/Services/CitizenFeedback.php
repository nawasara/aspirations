<?php

namespace Nawasara\Aspirations\Services;

use Illuminate\Support\Facades\DB;
use Nawasara\Aspirations\Exceptions\SubmissionException;
use Nawasara\Aspirations\Models\Report;
use Nawasara\Aspirations\Models\Response;
use Nawasara\Aspirations\Models\Support;
use Nawasara\Aspirations\Support\Settings;

use function app;

/**
 * Penilaian warga & dukungan "Saya Juga Mengalami".
 *
 * Inilah yang menutup lingkaran: laporan berangkat dari warga, dikerjakan OPD,
 * lalu kembali dinilai warga. Tanpa bagian ini, satu-satunya yang menilai
 * pekerjaan OPD adalah OPD sendiri.
 *
 * Penilaian juga penjaga terakhir atas keputusan D2: karena foto bukti tidak
 * diblokir, warga yang melihat lapangan tidak berubahlah yang menandainya.
 */
class CitizenFeedback
{
    /**
     * Warga menilai laporannya (#6).
     *
     * Nilai rendah MEMBUKA KEMBALI laporan — bukan sekadar mencatat
     * ketidakpuasan. Kalau hanya dicatat, warga belajar bahwa menilai tidak
     * mengubah apa pun, lalu berhenti menilai, dan angka kepuasan menjadi
     * kosong justru pada laporan yang paling bermasalah.
     */
    public function rate(Report $report, string $keycloakSub, int $rating, ?string $comment = null): Report
    {
        if ($report->keycloak_sub !== $keycloakSub) {
            throw new SubmissionException('Anda hanya dapat menilai laporan Anda sendiri.');
        }

        if ($report->status !== Report::STATUS_RESOLVED) {
            throw new SubmissionException('Laporan hanya dapat dinilai setelah dinyatakan selesai.');
        }

        if ($report->rating !== null) {
            // Sekali nilai. Kalau boleh diubah, OPD yang tidak puas dengan
            // penilaiannya dapat membujuk warga mengubahnya — dan angka
            // kepuasan berhenti mencerminkan pengalaman yang sesungguhnya.
            throw new SubmissionException('Laporan ini sudah pernah Anda nilai.');
        }

        if ($rating < 1 || $rating > 5) {
            throw new SubmissionException('Penilaian harus antara 1 sampai 5 bintang.');
        }

        return DB::transaction(function () use ($report, $rating, $comment) {
            $report->rating = $rating;

            $ambang = Settings::reopenThreshold();
            $dibukaKembali = $rating <= $ambang;

            if ($dibukaKembali) {
                // Kembali ke in_progress, BUKAN ke dispatched: OPD-nya sudah
                // benar dan sudah pernah mengerjakannya. Mengulang dari awal
                // hanya menambah satu putaran administrasi tanpa manfaat.
                $report->status = Report::STATUS_IN_PROGRESS;

                // Tenggat verifikasi dibersihkan — pekerjaannya belum
                // diserahkan ulang.
                $report->verification_due_at = null;
                $report->resolution_submitted_at = null;

                // ⚠️ `sla_due_at` TIDAK diperpanjang. Warga sudah menunggu
                // sekali; memberi OPD tenggat baru yang penuh berarti laporan
                // yang dikerjakan asal-asalan justru mendapat waktu tambahan.
                // Laporan ini akan langsung tampil sebagai terlambat, dan
                // memang seharusnya begitu.
            }

            $report->save();

            Response::create([
                'report_id' => $report->id,
                'user_id' => null,   // warga, bukan petugas
                'status_from' => Report::STATUS_RESOLVED,
                'status_to' => $report->status,
                'body' => $this->ratingBody($rating, $comment, $dibukaKembali),
                'is_internal' => false,
            ]);

            $report->refresh();

            if ($dibukaKembali) {
                // Diberitahukan supaya OPD tahu ada pekerjaan yang kembali —
                // laporan yang dibuka kembali diam-diam akan terlewat di
                // antrean yang sudah dianggap selesai.
                app(ReportNotifier::class)->reopened($report);
            }

            return $report;
        });
    }

    /**
     * "Saya Juga Mengalami" (#15).
     *
     * Mengurangi laporan ganda sekaligus menunjukkan mana yang dirasakan banyak
     * orang. Jumlahnya menaikkan prioritas — dan itu lebih jujur daripada
     * mengurutkan sekadar dari waktu masuk.
     */
    public function support(Report $report, string $keycloakSub): Report
    {
        if ($report->keycloak_sub === $keycloakSub) {
            throw new SubmissionException('Anda tidak dapat mendukung laporan Anda sendiri.');
        }

        if ($report->isClosed()) {
            throw new SubmissionException('Laporan ini sudah ditutup.');
        }

        return DB::transaction(function () use ($report, $keycloakSub) {
            // firstOrCreate + unique index di basis data: pemeriksaan di PHP
            // saja bisa lolos bila dua permintaan datang bersamaan, dan
            // hitungannya menjadi salah tanpa ada yang menyadari.
            $baru = Support::firstOrCreate([
                'report_id' => $report->id,
                'keycloak_sub' => $keycloakSub,
            ]);

            if ($baru->wasRecentlyCreated) {
                // increment() langsung di basis data, bukan baca-lalu-tulis —
                // dua dukungan bersamaan akan saling menimpa bila dihitung di
                // PHP.
                $report->increment('support_count');
            }

            return $report->refresh();
        });
    }

    /** Batalkan dukungan. */
    public function unsupport(Report $report, string $keycloakSub): Report
    {
        return DB::transaction(function () use ($report, $keycloakSub) {
            $terhapus = Support::where('report_id', $report->id)
                ->where('keycloak_sub', $keycloakSub)
                ->delete();

            if ($terhapus > 0 && $report->support_count > 0) {
                $report->decrement('support_count');
            }

            return $report->refresh();
        });
    }

    /**
     * Isi linimasa untuk penilaian.
     *
     * Ditulis dalam bahasa yang dibaca warga DAN petugas — keduanya melihat
     * linimasa yang sama, jadi tidak boleh berbunyi seperti catatan sistem.
     */
    protected function ratingBody(int $rating, ?string $comment, bool $dibukaKembali): string
    {
        $bintang = str_repeat('★', $rating).str_repeat('☆', 5 - $rating);

        $teks = "Penilaian warga: {$bintang} ({$rating}/5)";

        if ($comment !== null && trim($comment) !== '') {
            $teks .= "\n".trim($comment);
        }

        if ($dibukaKembali) {
            $teks .= "\n\nLaporan dibuka kembali karena penilaian di bawah batas.";
        }

        return $teks;
    }
}
