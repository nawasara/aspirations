<?php

namespace Nawasara\Aspirations\Services;

use Illuminate\Support\Collection;
use Nawasara\Aspirations\Models\Report;

/**
 * Deteksi laporan ganda: radius 50 m + kategori sama + 7 hari (#11).
 *
 * Tujuannya BUKAN memblokir. Warga yang melaporkan lubang yang sama dengan
 * tetangganya tidak sedang berbuat salah — mereka justru menunjukkan bahwa
 * masalahnya nyata. Yang ditawarkan adalah "Saya Juga Mengalami", sehingga
 * satu laporan menguat alih-alih tiga laporan bersaing.
 */
class DuplicateDetector
{
    /**
     * Cari laporan serupa yang masih berjalan.
     *
     * Mengembalikan koleksi kosong bila laporan tidak berkoordinat — tanpa
     * lokasi tidak ada dasar menyebut dua laporan sama. Lebih baik diam
     * daripada salah tuduh: pola yang sama sudah terbukti di `nawasara/hibah`,
     * di mana pencocokan tanpa alamat menghasilkan 1.769 positif palsu.
     */
    public function findSimilar(
        float $latitude,
        float $longitude,
        int $categoryId,
        ?int $excludeId = null,
    ): Collection {
        $radius = (int) config('nawasara-aspirations.duplicate.radius_meters', 50);
        $hari = (int) config('nawasara-aspirations.duplicate.window_days', 7);

        // Kotak pembatas dulu, baru jarak sesungguhnya. Tanpa ini setiap
        // pengiriman menghitung jarak ke SELURUH laporan sekategori — mahal,
        // dan makin mahal seiring data tumbuh.
        //
        // 1 derajat lintang ≈ 111.320 m di mana pun. Untuk bujur, jaraknya
        // menyempit mengikuti cos(lintang); di Ponorogo (≈ -7,87°) selisihnya
        // sekitar 1%, tetapi rumusnya tetap dipakai agar benar.
        $deltaLat = $radius / 111320;
        $deltaLng = $radius / (111320 * max(cos(deg2rad($latitude)), 0.01));

        $kandidat = Report::query()
            ->where('category_id', $categoryId)
            ->whereIn('status', Report::OPEN_STATUSES)
            ->where('created_at', '>=', now()->subDays($hari))
            ->whereBetween('latitude', [$latitude - $deltaLat, $latitude + $deltaLat])
            ->whereBetween('longitude', [$longitude - $deltaLng, $longitude + $deltaLng])
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->limit(20)
            ->get();

        return $kandidat->filter(function (Report $r) use ($latitude, $longitude, $radius) {
            if ($r->latitude === null || $r->longitude === null) {
                return false;
            }

            return $this->distanceMeters(
                $latitude, $longitude,
                (float) $r->latitude, (float) $r->longitude,
            ) <= $radius;
        })->values();
    }

    /**
     * Jarak dua titik dalam meter (haversine).
     *
     * Kotak pembatas di atas hanya penyaring kasar — sudutnya lebih jauh dari
     * radius yang diminta, jadi jarak sesungguhnya tetap harus dihitung.
     */
    public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;   // jari-jari bumi, meter

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
