<?php

namespace Nawasara\Aspirations\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nawasara\Aspirations\Models\District;
use Nawasara\Aspirations\Models\Village;

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

    /**
     * GET /api/v1/aspirations/regions/villages?district=350202
     *
     * ⚠️ Disaring per kecamatan, dan `district` WAJIB.
     *
     * Ponorogo punya 307 desa; mengirim semuanya sekaligus membuat daftar yang
     * tidak dapat ditelusuri warga di layar ponsel. Setelah kecamatan dipilih,
     * sisanya belasan — dan itu yang membuat memilih lebih cepat daripada
     * mengetik.
     */
    public function villages(Request $request): JsonResponse
    {
        $data = $request->validate([
            'district' => ['required', 'string', 'size:6'],
        ]);

        $villages = Village::query()
            ->where('district_code', $data['district'])
            ->orderBy('name')
            ->get(['code', 'name', 'is_kelurahan']);

        return response()->json([
            'data' => $villages->map(fn (Village $v) => [
                'code' => $v->code,
                'name' => $v->name,

                // Kelurahan berbeda dari desa: dipimpin lurah, tanpa
                // pemerintahan desa sendiri. Empat kecamatan memuat keduanya,
                // jadi aplikasi tidak dapat menyimpulkannya dari kecamatan.
                'is_kelurahan' => $v->is_kelurahan,
                'type' => $v->type_label,
            ])->all(),
        ]);
    }
}
