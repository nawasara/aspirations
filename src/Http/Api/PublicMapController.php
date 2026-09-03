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
    /**
     * Radius bawaan panel "Laporan di sekitar Anda", mengikuti mockup.
     */
    public const DEFAULT_RADIUS_METERS = 5000;

    public function __construct(protected PublicMap $map) {}

    /**
     * GET /api/v1/aspirations/reports/map
     * GET /api/v1/aspirations/reports/map?district=350213
     * GET /api/v1/aspirations/reports/map?lat=-7.8686&lng=111.4619&radius=5000
     *
     * Satu endpoint, tiga bentuk jawaban — ditentukan parameternya. Dijadikan
     * satu karena ketiganya adalah pertanyaan yang sama pada tingkat kedalaman
     * berbeda, dan aplikasi berpindah antara ketiganya dengan satu sentuhan.
     *
     * `lat`/`lng` OPSIONAL, dan tanpa keduanya jawabannya persis seperti
     * sebelumnya. Peta harus tetap bekerja bagi warga yang menolak memberikan
     * izin lokasi — dan sebagian memang menolak.
     *
     * ⚠️ Posisi warga hanya dipakai menghitung, tidak pernah disimpan. Yang
     * kembali adalah jaraknya saja, sudah dibulatkan; koordinat laporan tetap
     * tidak pernah keluar.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'district' => ['nullable', 'string', 'max:10'],
            'category' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:in_progress,resolved'],

            // Keduanya wajib berpasangan: satu koordinat saja tidak berarti
            // apa-apa, dan menerimanya diam-diam menghasilkan jarak yang
            // dihitung dari titik yang salah.
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],

            // Dibatasi 50 km — melampaui itu berarti seluruh kabupaten, dan
            // radiusnya tidak lagi menyaring apa pun.
            'radius' => ['nullable', 'integer', 'min:100', 'max:50000'],
        ]);

        $filters = [
            'category' => $data['category'] ?? null,
            'status' => $data['status'] ?? null,
        ];

        // Posisi warga → laporan terdekat, lintas kecamatan. Didahulukan atas
        // `district` karena warga di tepi kecamatan tetap perlu melihat
        // laporan seberang jalan yang kebetulan masuk wilayah lain.
        if (isset($data['lat'], $data['lng'])) {
            $reports = $this->map->reportsNear(
                (float) $data['lat'],
                (float) $data['lng'],
                isset($data['radius']) ? (int) $data['radius'] : self::DEFAULT_RADIUS_METERS,
                $filters,
            );

            return PublicReportResource::collection($reports)->response();
        }

        if (empty($data['district'])) {
            return response()->json([
                'data' => $this->map->districts($filters),
            ]);
        }

        $reports = $this->map->reportsIn($data['district'], $filters);

        return PublicReportResource::collection($reports)->response();
    }
}
