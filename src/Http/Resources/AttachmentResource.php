<?php

namespace Nawasara\Aspirations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Foto laporan / bukti penanganan.
 *
 * ⚠️ `path` (kunci objek di bucket) TIDAK pernah dikirim — hanya URL sementara.
 * Membocorkan kunci objek berarti membocorkan struktur penyimpanan, dan bucket
 * ini privat justru karena isinya wajah, pelat nomor, dan bagian dalam rumah
 * warga.
 *
 * Penanda keaslian (EXIF, source) juga TIDAK dikirim ke warga: itu bahan
 * pertimbangan Kabid saat memverifikasi, bukan informasi publik.
 */
class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'kind' => $this->kind,

            // Dibuat per permintaan dan kedaluwarsa. Null bila berkasnya hilang
            // atau disk belum terpasang — satu foto rusak tidak boleh
            // menjatuhkan seluruh halaman laporan.
            'url' => $this->temporaryUrl(),
        ];
    }
}
