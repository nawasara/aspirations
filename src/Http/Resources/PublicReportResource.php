<?php

namespace Nawasara\Aspirations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nawasara\Aspirations\Models\Report;
use Nawasara\Aspirations\Services\PublicMap;

/**
 * Laporan sebagaimana terlihat ORANG LAIN — bukan pelapornya.
 *
 * ⚠️ Ditulis sebagai DAFTAR-IZIN: hanya kolom yang disebut di bawah yang
 * keluar. Bukan `toArray()` lalu membuang beberapa — dengan cara itu, setiap
 * kolom baru yang ditambahkan kelak ikut terkirim secara bawaan, dan yang
 * bocor pertama kali biasanya justru yang paling tidak boleh.
 *
 * Empat hal sengaja TIDAK ADA, masing-masing karena alasannya sendiri:
 *
 *   reporter_name   Peta dibuka tanpa akun. Menyebut nama pelapor di depan
 *                   umum membuat orang berpikir dua kali sebelum melapor.
 *   description     Isi keluhan sering menyebut orang, alamat, atau nomor
 *                   kendaraan. Judul cukup untuk peta.
 *   photos          Foto memuat wajah, pelat nomor, dan bagian dalam rumah.
 *   koordinat persis Titik laporan menunjuk ke rumah seseorang. Yang keluar
 *                   hanya nama kecamatan — dan, bila warga mengirim posisinya,
 *                   JARAK yang sudah dibulatkan. Jarak tidak dapat dipakai
 *                   menemukan rumah siapa pun: lingkaran berjari-jari 240 m
 *                   memuat ratusan bangunan.
 *
 * Aplikasi sudah memutuskan tidak menampilkan keempatnya. Tidak
 * mengirimkannya membuat keputusan itu tidak dapat dibatalkan oleh perubahan
 * di sisi aplikasi kelak.
 *
 * @mixin Report
 */
class PublicReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // `code` — bukan `id`. Ia kunci publik yang memang dibagikan.
            'code' => $this->code,
            'status' => $this->status,
            'title' => $this->title,

            'category' => $this->whenLoaded('category', fn () => [
                'code' => $this->category->code,
                'name' => $this->category->name,
                'icon_name' => $this->category->icon_name,
                'color' => $this->category->color,
            ]),

            'opd_name' => $this->whenLoaded('opd', fn () => $this->opd?->name),

            'support_count' => (int) $this->support_count,

            'submitted_at' => $this->received_at?->toIso8601String(),

            // Hanya ada bila permintaan menyertakan posisi warga — pada
            // jawaban biasa kunci ini tidak muncul sama sekali, sehingga
            // bentuk lama tidak berubah bagi yang sudah memakainya.
            //
            // Bila diminta, ia SELALU ADA meski isinya `null`. Laporan tanpa
            // koordinat tetap membawa kuncinya, bukan menghilangkannya:
            // aplikasi membaca kolom ini sebagai nullable, dan kunci yang
            // kadang ada kadang tidak jauh lebih mudah membuatnya galat
            // daripada nilai kosong yang jujur.
            //
            // Nilainya SUDAH DIBULATKAN — jarak setepat meter dari beberapa
            // posisi dapat dipakai menghitung balik koordinat laporan.
            $this->mergeWhen($request->filled(['lat', 'lng']), fn () => [
                'distance_meters' => PublicMap::roundDistance(
                    isset($this->resource->distance_meters)
                        ? (float) $this->resource->distance_meters
                        : null
                ),
            ]),

            // Sengaja hanya nama wilayah. `village` berasal dari geocoding dan
            // boleh kosong — itu wajar, dan aplikasi menuliskannya nullable.
            'location' => [
                'village' => $this->village,
                'district' => $this->district_name,
            ],
        ];
    }
}
