<?php

namespace Nawasara\Aspirations\Services\Geocoding;

use Nawasara\Aspirations\Contracts\GeocodingProvider;

/**
 * Penyedia yang tidak melakukan apa-apa.
 *
 * Dipakai ketika kunci Google belum tersedia — dan itu keadaan yang SAH, bukan
 * kesalahan konfigurasi. Laporan tetap masuk, tetap didisposisi, tetap
 * ditangani; hanya kolom wilayahnya kosong.
 *
 * Dijadikan bawaan dengan sengaja: sistem yang menuntut kunci pihak ketiga
 * untuk sekadar menerima laporan warga adalah sistem yang mati begitu tagihan
 * telat dibayar.
 */
class NullGeocoder implements GeocodingProvider
{
    public function reverse(float $latitude, float $longitude): ?array
    {
        return null;
    }
}
