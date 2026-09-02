<?php

namespace Nawasara\Aspirations\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nawasara\Aspirations\Models\District;

/**
 * Daftar wilayah untuk pilihan alamat — kecamatan, kelak desa.
 *
 * ## Kenapa daftar, bukan isian bebas
 *
 * Alamat menentukan ke OPD mana laporan disalurkan. Selama kecamatan diketik
 * bebas, "Ngrayun", "Ngerayun", dan "Ngrayon" menjadi tiga kecamatan berbeda
 * di basis data padahal maksudnya satu — dan warga yang salah ketik tidak
 * pernah tahu laporannya salah alamat.
 *
 * Kecamatan bukan isian bebas: ia daftar tertutup berjumlah 21, ditetapkan
 * Kemendagri, dan tidak berubah.
 *
 * ## Kenapa di paket aspirations, bukan citizen
 *
 * Datanya sudah hidup di sini (`District`, dipakai peta laporan), dan
 * `aspirations` sudah bergantung pada `citizen` — memindahkannya ke citizen
 * berarti membalik arah itu dan membuat ketergantungan melingkar.
 *
 * ## Terbuka tanpa akun
 *
 * Sejalan dengan peta laporan dan nomor darurat: daftar kecamatan adalah
 * informasi publik, dan warga mengisi alamatnya justru **saat pertama
 * mendaftar** — ketika ia belum punya token.
 */
class RegionController
{
    /**
     * GET /api/v1/aspirations/regions/districts
     */
    public function districts(Request $request): JsonResponse
    {
        $districts = District::query()
            // Diurutkan menurut KODE, bukan abjad. Kode BPS berurut menurut
            // letak geografis, jadi kecamatan yang bertetangga berdekatan di
            // daftar — dan urutannya tidak berubah saat ada penambahan.
            ->orderBy('code')
            ->get(['code', 'name', 'latitude', 'longitude']);

        return response()->json([
            'data' => $districts->map(fn (District $d) => [
                'code' => $d->code,
                'name' => $d->name,

                // Koordinat titik tengah kecamatan — bukan titik laporan.
                // Aplikasi tidak membutuhkannya untuk daftar pilih, tetapi
                // memakainya untuk memusatkan peta, dan mengambilnya di sini
                // menghemat satu permintaan.
                'latitude' => $d->latitude !== null ? (float) $d->latitude : null,
                'longitude' => $d->longitude !== null ? (float) $d->longitude : null,
            ])->all(),
        ]);
    }
}
