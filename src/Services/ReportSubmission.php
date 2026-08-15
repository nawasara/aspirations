<?php

namespace Nawasara\Aspirations\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Nawasara\Aspirations\Exceptions\SubmissionException;
use Nawasara\Aspirations\Models\Category;
use Nawasara\Aspirations\Models\Report;
use Nawasara\Aspirations\Support\ReportCode;

/**
 * Penerimaan laporan warga — satu-satunya jalan membuat baris `reports`.
 *
 * Di sinilah disposisi otomatis terjadi (#18). Tidak ada lagi tahap "menunggu
 * Admin Kabupaten memeriksa": laporan langsung mendarat di dinas pengampu
 * kategorinya, sehingga hemat satu hari kerja sebelum sampai ke tangan yang
 * bisa menanganinya.
 *
 * Yang ditegakkan di sini:
 *   #11 deteksi laporan ganda (menawarkan, bukan memblokir)
 *   #12 maksimal 5 laporan per warga per hari
 *   #13 waktu dibuat di ponsel dipisah dari waktu diterima server
 *   #18 disposisi otomatis + stempel SLA, seketika
 *   #21 foto warga wajib dari kamera
 */
class ReportSubmission
{
    public function __construct(
        protected DuplicateDetector $duplicates,
    ) {}

    /**
     * Terima satu laporan.
     *
     * @param  array  $data  Sudah tervalidasi bentuknya oleh FormRequest;
     *                       yang diperiksa di sini adalah aturan BISNIS, yang
     *                       tidak boleh bergantung pada pemanggil.
     */
    public function submit(string $keycloakSub, array $data): Report
    {
        $category = Category::find($data['category_id'] ?? null);

        if (! $category || ! $category->is_active) {
            throw new SubmissionException('Kategori laporan tidak dikenali atau sudah tidak aktif.');
        }

        $this->assertWithinDailyLimit($keycloakSub);

        // Waktu DITERIMA server — dasar seluruh perhitungan SLA. Dihitung
        // sekali di sini supaya ketiga tenggat berangkat dari titik yang sama
        // persis, bukan dari now() yang dipanggil berkali-kali.
        $receivedAt = now();

        return DB::transaction(function () use ($keycloakSub, $data, $category, $receivedAt) {
            $report = new Report([
                // ⚠️ SELALU terisi, termasuk saat is_anonymous = true. "Anonim"
                // menyembunyikan NAMA di tampilan, bukan membuat pelapor tak
                // dikenal — batas harian dan penelusuran hukum bergantung pada
                // kolom ini.
                'keycloak_sub' => $keycloakSub,

                'code' => ReportCode::next($receivedAt),
                'category_id' => $category->id,
                'status' => Report::STATUS_SUBMITTED,
                'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
                'title' => $data['title'],
                'description' => $data['description'],
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'location_accuracy' => $data['location_accuracy'] ?? null,

                // #13 — dua waktu yang berbeda. Laporan mode luring dibuat
                // Senin dan sampai Rabu; keduanya disimpan agar kebijakan SLA
                // dapat ditetapkan kemudian tanpa kehilangan data lama.
                'created_at_device' => $this->parseDeviceTime($data['created_at_device'] ?? null, $receivedAt),
                'received_at' => $receivedAt,
            ]);

            $this->dispatchAndStampSla($report, $category, $receivedAt);

            $report->save();

            return $report;
        });
    }

    /**
     * Disposisi otomatis + stempel ketiga tenggat (#18).
     *
     * ⚠️ `sla_due_at` DISIMPAN, bukan dihitung saat dibaca. Bila dihitung
     * on-the-fly dari config, mengubah SLA sebuah kategori akan mengubah status
     * kepatuhan laporan lama secara surut — OPD yang tadinya tepat waktu
     * tiba-tiba tercatat terlambat.
     *
     * Kedua tenggat berangkat dari `received_at`, BUKAN berantai. Kalau
     * `sla_due_at` dihitung sejak OPD menanggapi, OPD yang lambat menanggapi
     * otomatis mendapat tenggat selesai yang mundur — persis terbalik dari
     * yang dimaksud.
     */
    protected function dispatchAndStampSla(Report $report, Category $category, Carbon $receivedAt): void
    {
        // Kategori tanpa OPD tetap diterima, TIDAK ditolak. Laporan warga tidak
        // boleh gagal karena registry belum lengkap — itu urusan kita, bukan
        // urusan mereka. Laporan menunggu dialihkan manual, dan `opd_id` kosong
        // membuatnya terlihat di panel Admin Kabupaten.
        $report->opd_id = $category->opd_id;

        $report->status = $category->opd_id
            ? Report::STATUS_DISPATCHED
            : Report::STATUS_SUBMITTED;

        $responseHours = (int) config('nawasara-aspirations.sla.response_hours', 72);

        $report->promised_sla_hours = $category->sla_hours;
        $report->response_due_at = $this->addHours($receivedAt, $responseHours, $category);
        $report->sla_due_at = $this->addHours($receivedAt, (int) $category->sla_hours, $category);
    }

    /**
     * Tambah jam, menghormati `uses_working_days` kategori.
     *
     * Default hari KALENDER: warga tidak membedakan hari libur ketika saluran
     * air mampet, dan SLA 3 jam mustahil memakai hari kerja.
     *
     * ⚠️ Mode hari kerja di sini baru melewati Sabtu-Minggu. Libur nasional
     * BELUM diperhitungkan — begitu ada kategori yang menyalakannya, daftar
     * libur wajib ada dan dipelihara tiap tahun, kalau tidak tenggat akan
     * meleset diam-diam di setiap hari besar.
     */
    protected function addHours(Carbon $from, int $hours, Category $category): Carbon
    {
        if (! $category->uses_working_days) {
            return $from->copy()->addHours($hours);
        }

        $at = $from->copy();
        $sisa = $hours;

        while ($sisa > 0) {
            $at->addHour();

            if (! $at->isWeekend()) {
                $sisa--;
            }
        }

        return $at;
    }

    /**
     * Batas laporan per warga per hari (#12).
     *
     * Dikunci pada `keycloak_sub`, jadi TETAP berlaku untuk laporan anonim —
     * sistem tahu siapa pengirimnya meski OPD tidak. Diperiksa di service,
     * bukan di middleware rate-limit: ini aturan bisnis yang harus berlaku
     * lewat jalur mana pun laporan masuk, termasuk impor dan panel.
     */
    protected function assertWithinDailyLimit(string $keycloakSub): void
    {
        $max = (int) config('nawasara-aspirations.limits.reports_per_day', 5);

        if ($max <= 0) {
            return;
        }

        $hariIni = Report::where('keycloak_sub', $keycloakSub)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if ($hariIni >= $max) {
            throw new SubmissionException(
                "Anda sudah mengirim {$max} laporan hari ini. Silakan lanjutkan besok."
            );
        }
    }

    /**
     * Waktu pembuatan di ponsel.
     *
     * Tidak dipercaya membabi buta: jam perangkat dapat salah setel atau
     * sengaja diubah. Waktu di masa depan, atau lebih tua dari 30 hari,
     * diabaikan dan dianggap sama dengan waktu terima — daripada menyimpan
     * angka yang akan membuat laporan kinerja tampak aneh tanpa sebab.
     */
    protected function parseDeviceTime(mixed $value, Carbon $receivedAt): Carbon
    {
        if (! $value) {
            return $receivedAt;
        }

        try {
            $at = Carbon::parse($value);
        } catch (\Throwable) {
            return $receivedAt;
        }

        if ($at->greaterThan($receivedAt) || $at->lessThan($receivedAt->copy()->subDays(30))) {
            return $receivedAt;
        }

        return $at;
    }

    /**
     * Laporan serupa yang masih berjalan — untuk DITAWARKAN, bukan memblokir.
     *
     * Dipanggil aplikasi sebelum mengirim, sehingga warga dapat memilih "Saya
     * Juga Mengalami" pada laporan yang sudah ada.
     */
    public function findSimilar(array $data): \Illuminate\Support\Collection
    {
        if (empty($data['latitude']) || empty($data['longitude'])) {
            return collect();
        }

        return $this->duplicates->findSimilar(
            (float) $data['latitude'],
            (float) $data['longitude'],
            (int) $data['category_id'],
        );
    }
}
