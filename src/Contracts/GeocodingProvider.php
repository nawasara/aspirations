<?php

namespace Nawasara\Aspirations\Contracts;

/**
 * Penerjemah koordinat menjadi alamat.
 *
 * Dijadikan kontrak, bukan panggilan langsung ke Google, karena tiga hal:
 *
 *   1. Kunci Google belum ada, dan menunggunya berarti menunda seluruh jalur
 *      ini — termasuk jalur GAGALNYA, yang justru paling perlu diuji.
 *   2. Google berbiaya per panggilan. Uji otomatis yang memanggilnya sungguhan
 *      adalah cara termudah menghabiskan anggaran tanpa hasil.
 *   3. Penyedia dapat berganti. Bila kelak dipakai Nominatim atau layanan
 *      dalam negeri, yang berubah hanya satu kelas.
 *
 * ⚠️ Implementasi WAJIB mengembalikan null bila gagal — jangan melempar.
 * Geocoding adalah pelengkap: laporan warga tetap sah tanpa nama wilayah, dan
 * kegagalan layanan pihak ketiga tidak boleh menjatuhkan laporan yang sudah
 * telanjur diterima.
 */
interface GeocodingProvider
{
    /**
     * Terjemahkan koordinat.
     *
     * @return array{full_address: string|null, village: string|null, district: string|null}|null
     *         null bila gagal atau tidak ditemukan.
     */
    public function reverse(float $latitude, float $longitude): ?array;
}
