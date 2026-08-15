<?php

namespace Nawasara\Aspirations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Kategori untuk aplikasi warga — kontrak 6.2 di rencana teknis.
 *
 * Tim Flutter memakainya sebagai patokan; jangan ubah nama kunci tanpa
 * memberitahu mereka, karena aplikasi yang sudah terpasang tidak ikut berubah.
 */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // Kunci pemetaan ikon di Flutter — stabil selamanya.
            'code' => $this->code,
            'name' => $this->name,

            // Contoh keluhan, membantu warga memilih kategori yang benar.
            'hint' => $this->hint,

            // Nama SIMBOLIK, bukan URL. Aplikasi memetakannya ke ikon yang
            // sudah dikompilasi; nama tak dikenal jatuh ke ikon "lainnya".
            'icon_name' => $this->icon_name,
            'color' => $this->color,

            // Dikirim sebagai ANGKA, bukan "14 hari kerja" — aplikasi yang
            // merangkai kalimatnya, sehingga bahasa dapat diubah tanpa merilis
            // ulang server.
            'sla_hours' => (int) $this->sla_hours,

            // Hanya nama untuk ditampilkan. `opd_id` sengaja TIDAK dikirim:
            // mengirimnya mengundang aplikasi ikut menentukan tujuan disposisi,
            // padahal itu aturan otorisasi milik server (#18).
            'opd_name' => $this->whenLoaded('opd', fn () => $this->opd?->name),

            'sort_order' => (int) $this->sort_order,
        ];
    }
}
