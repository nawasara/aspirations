<?php

namespace Nawasara\Aspirations\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nawasara\Aspirations\Exceptions\SubmissionException;
use Nawasara\Aspirations\Http\Resources\ReportResource;
use Nawasara\Aspirations\Models\Report;
use Nawasara\Aspirations\Models\Support;
use Nawasara\Aspirations\Services\CitizenFeedback;
use Nawasara\Aspirations\Services\PhotoUploader;
use Nawasara\Aspirations\Services\PublicMap;
use Nawasara\Aspirations\Services\ReportSubmission;
use Nawasara\Aspirations\Support\Settings;

/**
 * Endpoint laporan untuk APLIKASI WARGA.
 *
 * Di belakang `api.citizen` (JWT realm warga), bukan `api.auth`. Identitas
 * pelapor diambil dari token — TIDAK PERNAH dari badan permintaan. Menerima
 * `keycloak_sub` dari pemanggil berarti siapa pun dapat mengirim laporan atas
 * nama orang lain.
 */
class CitizenReportController extends Controller
{
    public function __construct(
        protected ReportSubmission $submission,
        protected PhotoUploader $photos,
        protected CitizenFeedback $feedback,
    ) {}

    /**
     * Laporan milik warga yang sedang masuk.
     *
     * Disaring pada `keycloak_sub` dari token, sehingga tidak ada cara meminta
     * laporan warga lain — tidak ada parameter yang bisa diutak-atik.
     */
    public function index(Request $request): JsonResponse
    {
        $sub = $this->citizenSub($request);

        $reports = Report::query()
            ->where('keycloak_sub', $sub)
            ->with(['category', 'opd'])
            ->latest('received_at')
            ->paginate(20);

        return ReportResource::collection($reports)->response();
    }

    /**
     * Detail satu laporan, beserta linimasa dan fotonya.
     *
     * Dicari lewat `code` (LB-2026-08-0412), bukan id — kode itulah yang
     * dipegang warga, dan id berurutan akan mengundang orang menebak laporan
     * milik orang lain.
     */
    public function show(Request $request, string $code): JsonResponse
    {
        $sub = $this->citizenSub($request);

        $report = Report::query()
            ->where('code', $code)
            ->where('keycloak_sub', $sub)
            ->with(['category', 'opd', 'responses' => fn ($q) => $q->public()->with('user'), 'attachments'])
            ->first();

        if (! $report) {
            // 404, bukan 403 — membedakan "tidak ada" dari "bukan milik Anda"
            // memberi tahu penebak bahwa kode itu ada.
            return response()->json([
                'error' => ['code' => 'not_found', 'message' => 'Laporan tidak ditemukan.'],
            ], 404);
        }

        return (new ReportResource($report))->response();
    }

    /**
     * Kirim laporan baru.
     *
     * Validasi bentuk di sini; aturan BISNIS (batas harian, disposisi, SLA) ada
     * di ReportSubmission supaya berlaku lewat jalur mana pun laporan masuk.
     */
    public function store(Request $request): JsonResponse
    {
        $sub = $this->citizenSub($request);

        $max = Settings::descriptionMax();

        $data = $request->validate([
            'category_id' => ['required', 'uuid', 'exists:nawasara_aspirations_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:'.$max],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0'],
            'is_anonymous' => ['nullable', 'boolean'],
            // Waktu pembuatan di ponsel — untuk laporan mode luring (#13).
            'created_at_device' => ['nullable', 'date'],
        ]);

        try {
            $report = $this->submission->submit($sub, $data);
        } catch (SubmissionException $e) {
            // Pesannya ditulis untuk dibaca warga; diteruskan apa adanya.
            return response()->json([
                'error' => ['code' => 'submission_rejected', 'message' => $e->getMessage()],
            ], 422);
        }

        $report->load(['category', 'opd']);

        return (new ReportResource($report))->response()->setStatusCode(201);
    }

    /**
     * Laporan serupa di sekitar titik yang dipilih.
     *
     * Dipanggil aplikasi SEBELUM mengirim, supaya warga dapat memilih "Saya
     * Juga Mengalami" alih-alih menambah laporan ketiga untuk lubang yang sama.
     */
    public function similar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'uuid'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        // Lewat saringan publik: laporan serupa milik ORANG LAIN, jadi aturan
        // yang sama dengan peta berlaku. Tanpa ini laporan berkategori sensitif
        // ikut ditawarkan beserta isi lengkapnya kepada siapa pun yang kebetulan
        // melapor di dekatnya.
        $similar = app(PublicMap::class)
            ->onlyVisible($this->submission->findSimilar($data))
            ->load(['category', 'opd']);

        return ReportResource::collection($similar)->response();
    }

    /**
     * Unggah foto ke laporan milik sendiri.
     *
     * Terpisah dari POST /reports supaya laporan tersimpan lebih dulu: warga
     * di sinyal lemah yang gagal mengunggah foto ketiga tidak kehilangan
     * seluruh laporannya.
     */
    public function uploadPhoto(Request $request, string $code): JsonResponse
    {
        $sub = $this->citizenSub($request);

        $data = $request->validate([
            'photo' => ['required', 'file', 'image'],
            // Dilaporkan aplikasi; diperiksa server (#21).
            'source' => ['nullable', 'string', 'in:camera,gallery'],
        ]);

        $report = Report::where('code', $code)->where('keycloak_sub', $sub)->first();

        if (! $report) {
            return response()->json([
                'error' => ['code' => 'not_found', 'message' => 'Laporan tidak ditemukan.'],
            ], 404);
        }

        try {
            $this->photos->storeReportPhoto(
                $report,
                $request->file('photo'),
                $data['source'] ?? 'camera',
            );
        } catch (SubmissionException $e) {
            return response()->json([
                'error' => ['code' => 'upload_rejected', 'message' => $e->getMessage()],
            ], 422);
        }

        $report->load(['category', 'opd', 'attachments']);

        return (new ReportResource($report))->response();
    }

    /**
     * Warga menilai laporannya (#6).
     *
     * Nilai rendah membuka kembali laporan — ditangani service, bukan di sini,
     * supaya aturannya berlaku lewat jalur mana pun penilaian masuk.
     */
    public function rate(Request $request, string $code): JsonResponse
    {
        $sub = $this->citizenSub($request);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $report = Report::where('code', $code)->first();

        if (! $report) {
            return $this->notFoundJson();
        }

        try {
            $report = $this->feedback->rate($report, $sub, $data['rating'], $data['comment'] ?? null);
        } catch (SubmissionException $e) {
            return response()->json([
                'error' => ['code' => 'rating_rejected', 'message' => $e->getMessage()],
            ], 422);
        }

        $report->load(['category', 'opd']);

        return (new ReportResource($report))->response();
    }

    /** "Saya Juga Mengalami" — mendukung laporan warga lain (#15). */
    public function support(Request $request, string $code): JsonResponse
    {
        $sub = $this->citizenSub($request);

        // Dicari TANPA menyaring pemilik: justru laporan orang lain yang
        // didukung. Yang dijaga service adalah larangan mendukung laporan
        // sendiri.
        //
        // Tetapi lewat saringan publik (Oktober 2026). Jawaban endpoint ini
        // memuat isi lengkap laporan, jadi tanpa saringan siapa pun dapat
        // membaca laporan berkategori sensitif cukup dengan "mendukungnya".
        // Laporan yang tidak boleh terlihat di peta juga tidak dapat didukung.
        $report = app(PublicMap::class)->findVisible($code);

        if (! $report) {
            return $this->notFoundJson();
        }

        try {
            $report = $this->feedback->support($report, $sub);
        } catch (SubmissionException $e) {
            return response()->json([
                'error' => ['code' => 'support_rejected', 'message' => $e->getMessage()],
            ], 422);
        }

        $report->load(['category', 'opd']);

        return (new ReportResource($report))->response();
    }

    /**
     * Detail laporan warga LAIN, untuk ikut mengawasi.
     *
     * Keputusan pemilik produk (7 Oktober 2026): laporan diawasi bersama, jadi
     * bentuknya SAMA dengan detail milik sendiri, termasuk deskripsi, foto,
     * dan linimasa. Peta yang menunjukkan "ada 21 laporan di Ngebel" tanpa
     * membiarkan siapa pun melihat apakah laporan itu ditangani belum
     * menjalankan fungsi pengawasan itu.
     *
     * Terpisah dari `show()` dengan sengaja. `show()` hanya untuk pemiliknya
     * dan menjawab 404 untuk laporan orang lain; menggabungkan keduanya dalam
     * satu fungsi mencampur dua aturan privasi yang berbeda.
     *
     * Yang tetap tertutup, lewat PublicMap: kategori sensitif, laporan yang
     * ditandai, dan laporan tanpa kecamatan. Ketiganya dijawab 404, sama
     * seperti kode yang tidak ada, supaya penebak tidak tahu kode itu ada.
     *
     * Wajib JWT warga (dipasang di grup rutenya): batas laju dihitung per
     * akun, sehingga menebak kode berurutan (LB-2026-10-0001, 0002, ...)
     * tidak dapat dilakukan tanpa nama.
     */
    public function showPublic(Request $request, string $code, PublicMap $map): JsonResponse
    {
        $sub = $this->citizenSub($request);

        $report = $map->findVisible($code);

        if (! $report) {
            return $this->notFoundJson();
        }

        // Pemuatan yang sama dengan show(), supaya bentuknya identik dan
        // aplikasi cukup punya satu layar detail.
        $report->load(['category', 'opd', 'responses' => fn ($q) => $q->public()->with('user'), 'attachments']);

        // Dua kolom tambahan yang hanya bermakna di sini:
        //
        //   is_supported  tombol "Saya juga mengalami" harus tahu keadaannya
        //                 sendiri; tanpa ini warga yang sudah mendukung melihat
        //                 tombol yang seolah belum ditekan.
        //   is_mine       laporan milik sendiri dapat juga dibuka dari peta;
        //                 aplikasi memakainya untuk tetap menampilkan Beri
        //                 Penilaian bagi pemiliknya.
        $report->setAttribute('is_supported', Support::where('report_id', $report->getKey())
            ->where('keycloak_sub', $sub)
            ->exists());
        $report->setAttribute('is_mine', $report->keycloak_sub === $sub);

        return (new ReportResource($report))->response();
    }

    /** Batalkan dukungan. */
    public function unsupport(Request $request, string $code): JsonResponse
    {
        $sub = $this->citizenSub($request);

        $report = app(PublicMap::class)->findVisible($code);

        if (! $report) {
            return $this->notFoundJson();
        }

        $report = $this->feedback->unsupport($report, $sub);
        $report->load(['category', 'opd']);

        return (new ReportResource($report))->response();
    }

    protected function notFoundJson(): JsonResponse
    {
        return response()->json([
            'error' => ['code' => 'not_found', 'message' => 'Laporan tidak ditemukan.'],
        ], 404);
    }

    /**
     * `sub` warga dari token yang sudah diverifikasi middleware.
     *
     * Diambil dari atribut request, bukan dari input — lihat catatan kelas.
     */
    protected function citizenSub(Request $request): string
    {
        return (string) $request->attributes->get('citizen_sub');
    }
}
