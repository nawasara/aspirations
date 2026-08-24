<?php

namespace Nawasara\Aspirations\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nawasara\Aspirations\Http\Resources\PublicReportResource;
use Nawasara\Aspirations\Services\PublicMap;

/**
 * Peta laporan publik — satu-satunya endpoint aspirations yang TERBUKA
 * tanpa akun.
 *
 * Keputusannya sejalan dengan Warta, CCTV, WiFi, dan Nomor Penting yang juga
 * terbuka: peta laporan adalah informasi publik, dan manfaatnya justru
 * berkurang bila hanya terlihat oleh yang sudah punya akun. Kontrol warga
 * atas penanganan laporan bekerja karena banyak mata, bukan karena mata yang
 * sudah mendaftar.
 *
 * **Mendukung laporan tetap menuntut akun** — itu tindakan, bukan bacaan, dan
 * tanpa identitas ia dapat digandakan tanpa batas.
 *
 * Seluruh penyaringan privasi ada di [PublicMap], bukan di sini.
 */
class PublicMapController
{
    public function __construct(protected PublicMap $map)
    {
    }

    /**
     * GET /api/v1/aspirations/reports/map
     * GET /api/v1/aspirations/reports/map?district=3502140
     *
     * Satu endpoint, dua bentuk jawaban — ditentukan ada tidaknya `district`.
     * Dijadikan satu karena keduanya adalah pertanyaan yang sama pada tingkat
     * kedalaman berbeda, dan aplikasi berpindah antara keduanya dengan satu
     * sentuhan.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'district' => ['nullable', 'string', 'max:10'],
            'category' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:in_progress,resolved'],
        ]);

        $filters = [
            'category' => $data['category'] ?? null,
            'status' => $data['status'] ?? null,
        ];

        if (empty($data['district'])) {
            return response()->json([
                'data' => $this->map->districts($filters),
            ]);
        }

        $reports = $this->map->reportsIn($data['district'], $filters);

        return PublicReportResource::collection($reports)->response();
    }
}
