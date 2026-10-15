<?php

namespace Nawasara\Aspirations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nawasara\Aspirations\Models\Report;

/**
 * Detail laporan milik warga LAIN, untuk ikut mengawasi.
 *
 * Dipakai `GET /reports/{code}/public` dan jawaban dukungan
 * (`POST/DELETE /reports/{code}/support`) bila pemanggil BUKAN pemiliknya.
 * Pemilik tetap menerima ReportResource lengkap.
 *
 * Audit keamanan 15 Oktober 2026, temuan T5. Keempat endpoint itu dulu
 * mengembalikan ReportResource milik pemilik: koordinat persis, alamat
 * lengkap, dan nama pelapor. Satu akun warga gratis cukup untuk mengumpulkan
 * nama warga beserta titik rumahnya, termasuk pelapor pungli yang lupa
 * mencentang anonim.
 *
 * Kuncinya SAMA dengan ReportResource, supaya aplikasi tetap memakai satu
 * model dan satu layar detail. Yang berbeda hanya isinya:
 *
 *   location        hanya `village` dan `district`. Tanpa latitude,
 *                   longitude, dan address: titik laporan menunjuk ke rumah
 *                   seseorang. Aplikasi menyusun sebutan tempat dari desa dan
 *                   kecamatan bila alamat kosong (ReportLocation.label).
 *   reporter_name   SELALU "Warga Ponorogo", anonim atau tidak. Bagi
 *                   pengawasan bersama yang penting adalah laporannya, bukan
 *                   siapa yang melapor.
 *   is_anonymous    tidak dikirim. Setelah nama selalu disamarkan, kunci ini
 *                   hanya memberi tahu siapa yang SENGAJA menyembunyikan diri.
 *
 * Deskripsi, foto, dan linimasa tetap dikirim sesuai keputusan pemilik produk
 * (7 Oktober 2026). Foto sudah tanpa EXIF sejak temuan S11 ditutup.
 *
 * Ditulis sebagai DAFTAR-IZIN, bukan turunan ReportResource yang membuang
 * beberapa kunci: kolom baru di ReportResource tidak boleh ikut ke sini tanpa
 * diputuskan.
 *
 * @mixin Report
 */
class OthersReportResource extends JsonResource
{
    /** Sebutan pelapor untuk warga lain, sama dengan sebutan anonim. */
    public const MASKED_REPORTER = 'Warga Ponorogo';

    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'status' => $this->status,
            'title' => $this->title,
            'description' => $this->description,

            'category' => [
                'code' => $this->whenLoaded('category', fn () => $this->category->code),
                'name' => $this->whenLoaded('category', fn () => $this->category->name),
                'icon_name' => $this->whenLoaded('category', fn () => $this->category->icon_name),
                'color' => $this->whenLoaded('category', fn () => $this->category->color),
            ],

            'location' => [
                'village' => $this->village,
                'district' => $this->district_name,
            ],

            'reporter_name' => self::MASKED_REPORTER,

            'opd_name' => $this->whenLoaded('opd', fn () => $this->opd?->name),
            'opd_code' => $this->whenLoaded('opd', fn () => $this->opd?->code),

            'submitted_at' => $this->received_at?->toIso8601String(),
            'promised_sla_hours' => $this->promised_sla_hours,
            'due_at' => $this->sla_due_at?->toIso8601String(),
            'resolved_at' => $this->verified_at?->toIso8601String(),
            'rating' => $this->rating,
            'support_count' => (int) $this->support_count,

            'timeline' => ResponseResource::collection($this->whenLoaded('responses')),
            'photos' => AttachmentResource::collection($this->whenLoaded('attachments')),

            $this->mergeWhen(array_key_exists('is_supported', $this->resource->getAttributes()), fn () => [
                'is_supported' => (bool) $this->resource->getAttribute('is_supported'),
                'is_mine' => (bool) $this->resource->getAttribute('is_mine'),
            ]),
        ];
    }
}
