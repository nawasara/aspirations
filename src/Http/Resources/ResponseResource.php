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

            // Nama dinas, bukan nama orangnya. Petugas berhak atas privasinya;
            // yang perlu warga tahu adalah unit mana yang menangani.
            'by' => $this->whenLoaded('user', fn () => $this->user?->name),

            'at' => $this->created_at?->toIso8601String(),
        ];
    }
}
