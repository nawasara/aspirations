<?php

namespace Nawasara\Aspirations\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Kecamatan Ponorogo — 21 baris tetap.
 *
 * Dipakai mengelompokkan laporan di peta, karena kolom `district` hasil
 * geocoding kosong pada seluruh laporan produksi. Lihat catatan migrasinya.
 */
class District extends Model
{
    protected $table = 'nawasara_aspirations_districts';

    protected $fillable = ['code', 'name', 'latitude', 'longitude'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /**
     * Kecamatan terdekat dari sebuah titik.
     *
     * ⚠️ Memakai jarak ke TITIK TENGAH, bukan batas wilayah sesungguhnya.
     * Untuk laporan di dekat perbatasan, hasilnya bisa meleset satu kecamatan.
     *
     * Itu diterima dengan sengaja: batas kecamatan yang sebenarnya menuntut
     * data poligon yang tidak dimiliki sistem ini, sementara peta hanya perlu
     * tahu "wilayah mana kira-kira" untuk menaruh titik. Ketelitian yang lebih
     * tinggi tidak mengubah apa pun yang dilihat warga, dan menuntut data yang
     * harus dirawat.
     *
     * Jarak dihitung dengan rumus haversine — bukan selisih derajat biasa,
     * yang di lintang Ponorogo membuat jarak bujur terhitung ~1% lebih jauh
     * daripada seharusnya dan dapat memilih kecamatan yang salah untuk titik
     * yang jaraknya berdekatan.
     */
    public static function nearest(float $latitude, float $longitude): ?self
    {
        $terdekat = null;
        $jarakTerdekat = PHP_FLOAT_MAX;

        foreach (self::all() as $district) {
            $jarak = self::haversine(
                $latitude,
                $longitude,
                $district->latitude,
                $district->longitude,
            );

            if ($jarak < $jarakTerdekat) {
                $jarakTerdekat = $jarak;
                $terdekat = $district;
            }
        }

        // Titik yang jauh dari SELURUH kecamatan berarti di luar Ponorogo —
        // laporan dari luar daerah, atau koordinat yang rusak. Lebih baik
        // tidak berkecamatan daripada dipaksakan ke kecamatan terluar.
        //
        // ⚠️ Ambangnya diukur dari jarak antar-kecamatan, BUKAN dari lebar
        // kabupaten. Dua kecamatan bertetangga paling jauh hanya 11,7 km
        // (Ngrayun), jadi titik mana pun di dalam Ponorogo pasti berada
        // dalam belasan kilometer dari salah satu titik tengah.
        //
        // 15 km dipilih supaya tetap menolak kabupaten tetangga, yang jauh
        // lebih dekat daripada dugaan: Trenggalek 14,1 km dan Madiun 21,1 km
        // dari kecamatan Ponorogo terdekat. Ambang 40 km — yang sempat
        // dipakai di sini — akan menarik laporan Madiun ke peta Ponorogo.
        if ($jarakTerdekat > 15.0) {
            return null;
        }

        return $terdekat;
    }

    /** Jarak dua titik dalam kilometer. */
    protected static function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
