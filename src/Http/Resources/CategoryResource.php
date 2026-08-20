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

            // Kode singkat untuk ruang sempit — daftar kategori di ponsel
            // hanya punya satu baris untuk ini, dan nama resmi panjangnya
            // ("DINAS PEKERJAAN UMUM, PERUMAHAN, DAN KAWASAN PERMUKIMAN")
            // terpotong sebelum bagian yang membedakannya terbaca.
            //
            // Aplikasi memilih sendiri mana yang dipakai; keduanya dikirim
            // supaya panel tetap dapat menampilkan nama resminya utuh.
            'opd_code' => $this->whenLoaded('opd', fn () => $this->opd?->code),

            // Aplikasi & panel perlu tahu di MUKA apakah foto bukti akan
            // diminta saat menutup laporan — supaya petugas tidak baru
            // mengetahuinya setelah menekan tombol selesai.
            'requires_evidence' => (bool) $this->requires_evidence,

            'sort_order' => (int) $this->sort_order,
        ];
    }
}
