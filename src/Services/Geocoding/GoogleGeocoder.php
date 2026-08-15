<?php

namespace Nawasara\Aspirations\Services\Geocoding;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Nawasara\Aspirations\Contracts\GeocodingProvider;

/**
 * Reverse geocoding lewat Google Maps Geocoding API.
 *
 * ⚠️ BERBIAYA PER PANGGILAN. Karena itu:
 *   - dipanggil dari Job, sekali per laporan, bukan setiap kali laporan dibaca
 *   - hasilnya DISIMPAN ke kolom, bukan diminta ulang
 *   - kegagalan tidak diulang tanpa batas (lihat GeocodeReportJob)
 *
 * Kunci API TIDAK PERNAH sampai ke aplikasi ponsel — inilah alasan geocoding
 * dikerjakan di server, bukan di Flutter. Kunci yang tertanam di APK dapat
 * dibongkar siapa pun, lalu dipakai orang lain atas tagihan Pemkab.
 */
class GoogleGeocoder implements GeocodingProvider
{
    public function reverse(float $latitude, float $longitude): ?array
    {
        $key = (string) config('nawasara-aspirations.geocoding.google_key', '');

        if ($key === '') {
            return null;
        }

        try {
            $res = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => $latitude.','.$longitude,
                'key' => $key,
                // Hasil dalam Bahasa Indonesia — nama wilayah ini ditampilkan
                // ke warga dan dipakai menyaring di panel.
                'language' => 'id',
                // Hanya tingkat yang berguna; membatasi ini mengurangi ukuran
                // jawaban sekaligus menghindari hasil "plus code" yang tidak
                // berarti apa-apa bagi petugas.
                'result_type' => 'street_address|administrative_area_level_4|administrative_area_level_3',
            ]);

            if ($res->failed()) {
                Log::warning('aspirations: geocoding gagal', ['status' => $res->status()]);

                return null;
            }

            $data = $res->json();

            // Google membalas 200 dengan status di dalam badan. ZERO_RESULTS
            // bukan kegagalan — titik di tengah sawah memang tidak punya
            // alamat, dan itu tidak perlu dicatat sebagai galat.
            if (($data['status'] ?? '') !== 'OK') {
                return null;
            }

            return $this->mapComponents($data['results'][0] ?? []);
        } catch (\Throwable $e) {
            // Tidak melempar — lihat kontrak GeocodingProvider.
            Log::warning('aspirations: geocoding error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Ambil desa & kecamatan dari komponen alamat.
     *
     * Di Indonesia, Google memetakan:
     *   administrative_area_level_4 → kelurahan/desa
     *   administrative_area_level_3 → kecamatan
     *
     * Keduanya diambil terpisah, bukan dipotong dari `formatted_address`:
     * susunan teksnya berbeda antar-daerah, dan memotong string adalah cara
     * mendapatkan "Jawa Timur" sebagai nama desa.
     */
    protected function mapComponents(array $result): ?array
    {
        if ($result === []) {
            return null;
        }

        $ambil = function (string $tipe) use ($result): ?string {
            foreach ($result['address_components'] ?? [] as $k) {
                if (in_array($tipe, $k['types'] ?? [], true)) {
                    return $k['long_name'] ?? null;
                }
            }

            return null;
        };

        return [
            'full_address' => $result['formatted_address'] ?? null,
            'village' => $ambil('administrative_area_level_4'),
            'district' => $ambil('administrative_area_level_3'),
        ];
    }
}
