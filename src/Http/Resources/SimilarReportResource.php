<?php

namespace Nawasara\Aspirations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nawasara\Aspirations\Models\Report;
use Nawasara\Aspirations\Services\PublicMap;

/**
 * Satu laporan serupa (`GET /reports/similar`), ditawarkan SEBELUM warga
 * mengirim laporannya sendiri.
 *
 * Audit keamanan 15 Oktober 2026, temuan T5. Endpoint ini dulu mengembalikan
 * ReportResource lengkap: koordinat persis, alamat, nama pelapor, dan
 * deskripsi laporan orang lain. Karena titiknya dipilih pemanggil, ia dapat
 * dipakai menyapu wilayah.
 *
 * Yang dikirim hanya yang dibutuhkan kartu "Saya juga mengalami": kode, judul,
 * status, ikon kategori, jumlah dukungan, dan JARAK yang sudah dibulatkan
 * (PublicMap::roundDistance, aturan yang sama dengan peta dan linimasa). Jarak
 * dihitung server dari titik yang dikirim pemanggil, jadi aplikasi tidak lagi
 * membutuhkan koordinat laporan untuk menghitungnya sendiri.
 *
 * DAFTAR-IZIN: kolom baru di Report tidak ikut keluar tanpa diputuskan di sini.
 *
 * @mixin Report
 */
class SimilarReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->getAttributes();

        return [
            'code' => $this->code,
            'title' => $this->title,
            'status' => $this->status,

            'category' => [
                'code' => $this->category?->code,
                'name' => $this->category?->name,
                'icon_name' => $this->category?->icon_name,
                'color' => $this->category?->color,
            ],

            'support_count' => (int) $this->support_count,

            'distance_meters' => PublicMap::roundDistance(
                isset($attributes['distance_meters']) ? (float) $attributes['distance_meters'] : null
            ),
        ];
    }
}
