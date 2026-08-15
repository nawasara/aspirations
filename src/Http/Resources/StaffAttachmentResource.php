<?php

namespace Nawasara\Aspirations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Foto untuk mata KABID saat memverifikasi.
 *
 * Berbeda dari AttachmentResource: penanda keaslian ikut dikirim. Inilah yang
 * membuat aturan #22 berarti sama sekali — penanda yang tersimpan di basis
 * data tetapi tidak pernah muncul di layar tidak menjaga apa pun.
 */
class StaffAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'kind' => $this->kind,
            'url' => $this->temporaryUrl(),

            // Penanda, BUKAN blokir (D2). Foto bukti boleh dari galeri —
            // petugas bisa saja mengunggah dari kantor karena sinyal buruk di
            // lapangan, dan memblokirnya akan menghukum orang yang bekerja
            // jujur.
            'source' => $this->source,
            'captured_at' => $this->captured_at?->toIso8601String(),

            // ⚠️ `null` di sini berarti TIDAK DIKETAHUI, bukan mencurigakan.
            // WhatsApp membuang EXIF, jadi foto bukti yang sampai lewat WA akan
            // sering kosong. Panel harus menampilkannya sebagai "tidak ada
            // data", bukan sebagai peringatan — kalau tidak, hampir semua foto
            // tertandai dan petugas belajar mengabaikan penandanya.
            'suspect' => $this->isSuspect(),
        ];
    }
}
