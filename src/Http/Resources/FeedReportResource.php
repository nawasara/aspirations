<?php

namespace Nawasara\Aspirations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nawasara\Aspirations\Services\PublicMap;

/**
 * Satu kiriman di linimasa laporan warga (`GET /reports/feed`).
 *
 * Kuncinya sama dengan ReportResource, supaya aplikasi memakai satu model
 * untuk linimasa dan detail. Yang berbeda hanya apa yang TIDAK dikirim:
 *
 *   reporter_name, is_anonymous   kepala kiriman adalah kategori dan desa,
 *                                 bukan orangnya.
 *   latitude, longitude, address  dua puluh laporan per halaman berarti
 *                                 seluruh kabupaten dapat disedot halaman
 *                                 demi halaman. Dan bila koordinat persis
 *                                 ikut terkirim, membulatkan
 *                                 `distance_meters` tidak lagi melindungi
 *                                 apa pun.
 *   timeline                      dibaca di detail (`/reports/{code}/public`).
 *
 * Detail publik tetap mengirim ketiganya, seperti yang sudah diputuskan; yang
 * dibatasi di sini adalah penarikan massal, bukan pembacaan satu laporan.
 *
 * Ditulis sebagai DAFTAR-IZIN, bukan turunan ReportResource yang membuang
 * beberapa kunci: kolom baru di ReportResource tidak boleh ikut ke linimasa
 * tanpa diputuskan di sini.
 */
class FeedReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->getAttributes();

        return [
            'code' => $this->code,
            'status' => $this->status,
            'title' => $this->title,

            // Utuh. Aplikasi memotongnya jadi dua baris; memotong di server
            // memutus kata di tengah.
            'description' => $this->description,

            'category' => [
                'code' => $this->category?->code,
                'name' => $this->category?->name,
                'icon_name' => $this->category?->icon_name,
                'color' => $this->category?->color,
            ],

            'location' => [
                'village' => $this->village,
                'district' => $this->district_name,
            ],

            'opd_name' => $this->opd?->name,
            'opd_code' => $this->opd?->code,

            'submitted_at' => $this->received_at?->toIso8601String(),
            'promised_sla_hours' => $this->promised_sla_hours,
            'due_at' => $this->sla_due_at?->toIso8601String(),
            'resolved_at' => $this->verified_at?->toIso8601String(),
            'rating' => $this->rating,
            'support_count' => (int) $this->support_count,

            'is_supported' => (bool) ($attributes['is_supported'] ?? false),
            'is_mine' => (bool) ($attributes['is_mine'] ?? false),

            // Hanya ada bila warga mengirim lat/lng. Dibulatkan dengan aturan
            // yang sama dengan peta; jarak presisi dari beberapa titik dapat
            // ditrilaterasi menjadi koordinat laporan.
            $this->mergeWhen(array_key_exists('distance_meters', $attributes), fn () => [
                'distance_meters' => PublicMap::roundDistance(
                    $attributes['distance_meters'] !== null ? (float) $attributes['distance_meters'] : null
                ),
            ]),

            'photos' => AttachmentResource::collection($this->attachments),
        ];
    }
}
