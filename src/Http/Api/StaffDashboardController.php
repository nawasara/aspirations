<?php

namespace Nawasara\Aspirations\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nawasara\Aspirations\Services\DashboardSummary;

/**
 * Ringkasan dashboard dalam SATU permintaan.
 *
 * Menggantikan 30 permintaan beruntun yang menarik 3.000 baris lengkap hanya
 * untuk menggambar belasan angka — beserta `timeline`, `photos`, dan
 * `location` yang tak satu pun dipakai.
 */
class StaffDashboardController
{
    public function __construct(
        protected DashboardSummary $summary,
    ) {}

    /**
     * GET /api/v1/staff/aspirations/dashboard/summary?period=2026-09
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            // `YYYY-MM` atau `all`. Divalidasi bentuknya supaya bulan yang
            // salah ketik menjawab 422, bukan diam-diam menghasilkan angka nol
            // yang terbaca seperti "tidak ada laporan bulan itu".
            'period' => ['nullable', 'string', 'regex:/^(all|\d{4}-\d{2})$/'],
        ]);

        return response()->json([
            'data' => $this->summary->build($data['period'] ?? 'all'),
        ]);
    }
}
