<?php

namespace Nawasara\Aspirations\Support;

/**
 * Asal titik lokasi laporan: GPS ponsel, atau ditunjuk warga di peta.
 *
 * Tiga keadaan yang menuntut tindakan berbeda dari petugas:
 *
 *   gps     dikunci saat rana ditekan; `location_accuracy` dalam meter
 *   manual  warga menunjuk di peta; titiknya PERKIRAAN, akurasinya null
 *   null    tidak ada titik sama sekali; andalkan alamat tertulis
 *
 * Tanpa pembeda ini titik tunjukan tersamar sebagai titik GPS, dan petugas
 * berangkat ke lapangan memercayai akurasi yang tidak pernah ada.
 *
 * Fungsi murni, tanpa basis data, supaya aturannya dapat diuji persis.
 */
final class LocationSource
{
    public const GPS = 'gps';

    public const MANUAL = 'manual';

    public const ALL = [self::GPS, self::MANUAL];

    /**
     * Nilai yang disimpan untuk `location_source` dan `location_accuracy`.
     *
     * - Tanpa titik → sumbernya null, apa pun yang dikirim. "Tidak ada titik"
     *   bukan GPS dan bukan tunjukan; menyimpannya sebagai `gps` membuat
     *   laporan tanpa koordinat terlihat seperti laporan GPS yang rusak.
     * - Ada titik, kunci tidak dikirim → `gps`. Aplikasi versi lama hanya
     *   mampu mengirim titik GPS (kontrak API: warga tidak dapat menggeser
     *   titik), jadi laporannya tetap terbaca persis seperti sebelumnya.
     * - `manual` → akurasi DIBUANG menjadi null. Angka apa pun di sana (0, 999,
     *   radius peta) mengembalikan masalah yang hendak diselesaikan kunci ini.
     *   Dibuang, bukan ditolak: menolak berarti warga kehilangan laporannya
     *   karena kekeliruan aplikasi, padahal titik dan ceritanya sah.
     *
     * @return array{source: ?string, accuracy: ?float}
     */
    public static function normalize(mixed $source, mixed $latitude, mixed $longitude, mixed $accuracy): array
    {
        $hasPoint = $latitude !== null && $latitude !== '' && $longitude !== null && $longitude !== '';

        if (! $hasPoint) {
            return ['source' => null, 'accuracy' => null];
        }

        $source = in_array($source, self::ALL, true) ? $source : self::GPS;

        if ($source === self::MANUAL) {
            return ['source' => self::MANUAL, 'accuracy' => null];
        }

        return [
            'source' => self::GPS,
            'accuracy' => ($accuracy === null || $accuracy === '') ? null : (float) $accuracy,
        ];
    }
}
