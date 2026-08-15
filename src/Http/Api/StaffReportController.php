<?php

namespace Nawasara\Aspirations\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nawasara\Aspirations\Exceptions\WorkflowException;
use Nawasara\Aspirations\Http\Resources\StaffReportResource;
use Nawasara\Aspirations\Models\Report;
use Nawasara\Aspirations\Services\ReportWorkflow;

/**
 * Endpoint laporan untuk PANEL STAF (Next.js).
 *
 * Di belakang `api.staff` (JWT realm pegawai), yang sudah memetakan token ke
 * baris `users` dan menyalakan Auth::setUser(). Karena itu `ScopedToOpd` pada
 * model bekerja di sini persis seperti di panel Livewire — Admin OPD hanya
 * melihat laporan miliknya tanpa satu pun where-clause di controller ini.
 *
 * ⚠️ Menyembunyikan tombol di panel BUKAN penegakan. Setiap aksi di bawah
 * memeriksa izinnya sendiri, karena permintaan dapat dikirim langsung ke API
 * tanpa melewati antarmuka mana pun.
 */
class StaffReportController extends Controller
{
    public function __construct(
        protected ReportWorkflow $workflow,
    ) {}

    /**
     * Antrean laporan.
     *
     * Tidak ada `where('opd_id', …)` di sini — global scope yang mengerjakannya.
     * Menuliskannya lagi di controller justru berbahaya: memberi kesan
     * penyaringan ada di sini, sehingga endpoint berikutnya yang lupa
     * menuliskannya terlihat wajar.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('aspirations.report.view');

        $data = $request->validate([
            'status' => ['nullable', 'string'],
            'overdue' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $reports = Report::query()
            ->with(['category', 'opd', 'responder', 'verifier'])
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($data['overdue'] ?? null, fn ($q) => $q->overdue())
            ->when($data['q'] ?? null, fn ($q, $cari) => $q->where(function ($x) use ($cari) {
                $x->where('code', 'like', "%{$cari}%")
                  ->orWhere('title', 'like', "%{$cari}%");
            }))
            // Tenggat terdekat di atas: yang paling genting terlihat lebih dulu,
            // tanpa petugas harus memilih urutan.
            ->orderByRaw('sla_due_at IS NULL, sla_due_at ASC')
            ->paginate(25);

        return StaffReportResource::collection($reports)->response();
    }

    /**
     * Antrean verifikasi milik Kabid yang sedang masuk.
     *
     * Difilter pada `verifier_id`, bukan hanya status — Kabid hanya melihat
     * yang DITUJUKAN kepadanya (D1b), bukan seluruh laporan OPD yang menunggu
     * verifikasi.
     */
    public function verificationQueue(Request $request): JsonResponse
    {
        $this->authorize('aspirations.report.verify');

        $reports = Report::query()
            ->awaitingVerificationBy((int) $request->user()->getAuthIdentifier())
            ->with(['category', 'opd', 'responder', 'attachments'])
            ->orderBy('verification_due_at')
            ->paginate(25);

        return StaffReportResource::collection($reports)->response();
    }

    public function show(Request $request, string $code): JsonResponse
    {
        $this->authorize('aspirations.report.view');

        $report = Report::query()
            ->where('code', $code)
            ->with(['category', 'opd', 'responder', 'verifier', 'attachments',
                    'responses' => fn ($q) => $q->with('user')])
            ->first();

        // Global scope sudah menyaring OPD lain, jadi laporan milik dinas lain
        // sampai di sini sebagai "tidak ada" — bukan sebagai 403. Itu memang
        // yang diinginkan: keberadaannya pun bukan urusan mereka.
        if (! $report) {
            return $this->notFound();
        }

        return (new StaffReportResource($report))->response();
    }

    /** Staf mulai mengerjakan — mengisi first_responded_at (response time). */
    public function startWork(Request $request, string $code): JsonResponse
    {
        $this->authorize('aspirations.report.respond');

        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        return $this->run($code, fn (Report $r) => $this->workflow
            ->startWork($r, $request->user(), $data['body']));
    }

    /**
     * Staf menyerahkan pekerjaan ke Kabid pilihannya (D1b).
     *
     * `verifier_id` datang dari panel karena petugaslah yang tahu siapa
     * atasannya — tetapi siapa yang BOLEH ditunjuk tetap diputuskan server
     * (#16, #25, #26), bukan oleh daftar yang ditampilkan panel.
     */
    public function submitForVerification(Request $request, string $code): JsonResponse
    {
        $this->authorize('aspirations.report.respond');

        $data = $request->validate([
            'verifier_id' => ['required', 'integer', 'exists:users,id'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $model = config('auth.providers.users.model');
        $verifier = $model::find($data['verifier_id']);

        return $this->run($code, fn (Report $r) => $this->workflow
            ->submitForVerification($r, $request->user(), $verifier, $data['body']));
    }

    /** Kabid menyetujui — laporan selesai. */
    public function approve(Request $request, string $code): JsonResponse
    {
        // Izin & aturan #16/#3 diperiksa di dalam workflow, pada
        // PERPINDAHANNYA — bukan di sini. Memeriksanya dua kali di dua tempat
        // yang berbeda adalah cara aturan lambat laun berbeda isinya.
        return $this->run($code, fn (Report $r) => $this->workflow
            ->approve($r, $request->user()));
    }

    /** Kabid mengembalikan pekerjaan ke staf, dengan alasan. */
    public function rejectWork(Request $request, string $code): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        return $this->run($code, fn (Report $r) => $this->workflow
            ->rejectWork($r, $request->user(), $data['reason']));
    }

    /**
     * Jalankan satu aksi alur kerja.
     *
     * Menyeragamkan tiga hal yang mudah tercecer bila ditulis berulang: laporan
     * dicari lewat scope yang sama, penolakan alur dijawab 422 dengan pesan
     * yang dibaca petugas, dan jawaban suksesnya berbentuk sama.
     */
    protected function run(string $code, callable $action): JsonResponse
    {
        $report = Report::where('code', $code)->first();

        if (! $report) {
            return $this->notFound();
        }

        try {
            $report = $action($report);
        } catch (WorkflowException $e) {
            return response()->json([
                'error' => ['code' => 'workflow_rejected', 'message' => $e->getMessage()],
            ], 422);
        }

        $report->load(['category', 'opd', 'responder', 'verifier']);

        return (new StaffReportResource($report))->response();
    }

    protected function notFound(): JsonResponse
    {
        return response()->json([
            'error' => ['code' => 'not_found', 'message' => 'Laporan tidak ditemukan.'],
        ], 404);
    }

    /** Gagal izin dijawab 403 JSON, bukan halaman galat HTML. */
    protected function authorize(string $permission): void
    {
        if (! request()->user()?->can($permission)) {
            abort(response()->json([
                'error' => ['code' => 'forbidden', 'message' => 'Anda tidak berwenang melakukan tindakan ini.'],
            ], 403));
        }
    }
}
