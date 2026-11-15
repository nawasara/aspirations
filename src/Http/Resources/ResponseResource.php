<?php

namespace Nawasara\Aspirations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Satu langkah di linimasa laporan.
 *
 * Menampilkan PELAKU ("oleh: Admin DPUPKP") supaya warga tahu ada manusia yang
 * menangani, bukan sistem anonim — mockup DetailLaporan sudah menyiapkan
 * ruangnya.
 */
class ResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status_to,
            'body' => $this->body,

            // Catatan antar-petugas. Warga tidak pernah menerimanya —
            // CitizenReportController memuat scope public() — jadi bagi warga
            // nilai ini selalu false; panel staf memakainya untuk menandai.
            'internal' => (bool) $this->is_internal,

            // Nama dinas, bukan nama orangnya. Petugas berhak atas privasinya;
            // yang perlu warga tahu adalah unit mana yang menangani.
            'by' => $this->whenLoaded('user', fn () => $this->user?->name),

            'at' => $this->created_at?->toIso8601String(),
        ];
    }
}
