<?php

namespace Nawasara\Aspirations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bentuk laporan yang dikirim ke luar.
 *
 * ⚠️ INILAH tempat identitas pelapor anonim disaring (#8) — bukan di
 * antarmuka. Menyembunyikannya di panel berarti datanya tetap terkirim ke
 * browser dan terbaca siapa pun yang membuka panel jaringan peramban. Dengan
 * panel Next.js, itu makin gampang dilihat.
 *
 * Bila aturan ini bocor sekali saja, warga berhenti melaporkan pungli — dan
 * justru kategori itu yang paling bernilai bagi pimpinan.
 *
 * Ditulis sebagai DAFTAR-IZIN: hanya kolom yang disebut di sini yang keluar.
 * Kalau memakai `$this->resource->toArray()` lalu membuang beberapa kolom,
 * setiap kolom baru di masa depan otomatis ikut terkirim — termasuk yang
 * seharusnya tidak.
 */
class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // Kode publik, bukan id basis data — inilah yang disebut warga saat
            // menanyakan laporannya.
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
                'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
                'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
                'address' => $this->full_address,
                'village' => $this->village,
                'district' => $this->district,
            ],

            'is_anonymous' => (bool) $this->is_anonymous,

            // Nama pelapor — DISARING di sini. `keycloak_sub` tidak pernah
            // keluar dalam keadaan apa pun; ia kunci internal, bukan data yang
            // perlu diketahui pemanggil mana pun.
            'reporter_name' => $this->reporterName(),

            // Dinas yang menangani. Hanya namanya; `opd_id` tidak dikirim
            // supaya aplikasi tidak pernah ikut menentukan tujuan disposisi —
            // itu keputusan server (#18).
            'opd_name' => $this->whenLoaded('opd', fn () => $this->opd?->name),

            'submitted_at' => $this->received_at?->toIso8601String(),

            // Janji yang ditampilkan saat mengirim — dikirim sebagai ANGKA,
            // bukan kalimat jadi, supaya bahasanya dapat diubah tanpa merilis
            // ulang server.
            'promised_sla_hours' => $this->promised_sla_hours,
            'due_at' => $this->sla_due_at?->toIso8601String(),

            'resolved_at' => $this->verified_at?->toIso8601String(),
            'rating' => $this->rating,
            'support_count' => (int) $this->support_count,

            'timeline' => ResponseResource::collection(
                $this->whenLoaded('responses')
            ),

            'photos' => AttachmentResource::collection(
                $this->whenLoaded('attachments')
            ),
        ];
    }

    /**
     * Nama pelapor, atau sebutan pengganti bila anonim.
     *
     * "Anonim" menyembunyikan NAMA, bukan menjadikan pelapor tak dikenal —
     * `keycloak_sub` tetap tersimpan di basis data. Yang tidak boleh adalah
     * nama itu sampai ke panel OPD.
     */
    protected function reporterName(): string
    {
        if ($this->is_anonymous) {
            return 'Warga Ponorogo';
        }

        // Nama diambil dari profil warga bila relasinya dimuat; bila tidak,
        // jangan menebak — sebutan netral lebih baik daripada nama yang salah.
        return $this->citizen_name ?? 'Warga Ponorogo';
    }
}
