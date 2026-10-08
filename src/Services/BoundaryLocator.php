<?php

namespace Nawasara\Aspirations\Services;

use Nawasara\Aspirations\Models\Report;
use Nawasara\Aspirations\Models\Village;

/**
 * Desa dan kecamatan dari sebuah titik, menurut BATAS wilayah sesungguhnya.
 *
 * Menggantikan District::nearest(), yang memilih titik tengah kecamatan
 * terdekat. Cara itu meleset di setiap perbatasan: titik tengah tidak pernah
 * berada tepat di tengah wilayah, sehingga jalur selebar beberapa kilometer
 * di tepi satu kecamatan tercatat sebagai tetangganya. LB-2026-09-0014 diambil
 * di Slahung, koordinat kameranya benar, dan tersimpan sebagai Balong.
 *
 * Itu sempat diterima karena kecamatan dulu hanya menaruh titik di peta.
 * Sekarang ia dipakai statistik per wilayah, saringan panel, dan linimasa,
 * dan kelak mungkin penyaluran ke kantor kecamatan. Di situ "kira-kira"
 * tidak cukup.
 *
 * Batasnya dari BIG (pembaruan Juni 2023, kode Kemendagri), disimpan di
 * `database/boundaries/ponorogo-villages.json`; lihat `build.php` di folder
 * yang sama untuk asal dan cara membangunnya ulang. Ke-307 kode desanya
 * cocok persis dengan master wilayah.
 *
 * Batas DESA, bukan kecamatan: satu pemeriksaan memberi keduanya, dan kolom
 * `village` yang selama ini selalu kosong ikut terisi.
 */
class BoundaryLocator
{
    /**
     * Titik di luar semua desa tetapi sedekat ini dari salah satunya tetap
     * dianggap di desa itu.
     *
     * Ada dua penyebab yang sah: GPS ponsel meleset belasan meter, dan garis
     * batas yang disederhanakan (±3 m) menyisakan celah sempit di antara dua
     * desa bertetangga. Yang lebih jauh dari ini berarti memang di luar
     * Ponorogo, dan lebih jujur dibiarkan tanpa wilayah daripada dipaksakan
     * ke desa terluar.
     */
    public const SNAP_METERS = 200;

    /** @var array<int, array{code: string, district_code: string, name: string, bbox: array<int, float>, polygons: array}>|null */
    protected static ?array $villages = null;

    /**
     * @return array{village_code: string, district_code: string, village_name: string, inside: bool, distance_meters: int}|null
     *         `inside` false berarti ditempelkan ke desa terdekat (lihat SNAP_METERS)
     */
    public function locate(float $latitude, float $longitude): ?array
    {
        $villages = $this->villages();

        foreach ($villages as $v) {
            [$minLng, $minLat, $maxLng, $maxLat] = $v['bbox'];

            if ($longitude < $minLng || $longitude > $maxLng || $latitude < $minLat || $latitude > $maxLat) {
                continue;
            }

            if ($this->insideMultiPolygon($longitude, $latitude, $v['polygons'])) {
                return $this->result($v, true, 0);
            }
        }

        // Tidak di dalam desa mana pun: cari tepi terdekat. Kotak pembatas
        // diperlebar SNAP_METERS supaya hanya desa yang mungkin yang dihitung.
        $padLat = self::SNAP_METERS / 110574;
        $padLng = self::SNAP_METERS / (111320 * cos(deg2rad($latitude)));

        $best = null;
        $bestDistance = INF;

        foreach ($villages as $v) {
            [$minLng, $minLat, $maxLng, $maxLat] = $v['bbox'];

            if ($longitude < $minLng - $padLng || $longitude > $maxLng + $padLng
                || $latitude < $minLat - $padLat || $latitude > $maxLat + $padLat) {
                continue;
            }

            $d = $this->distanceToEdges($longitude, $latitude, $v['polygons']);

            if ($d < $bestDistance) {
                $bestDistance = $d;
                $best = $v;
            }
        }

        if ($best === null || $bestDistance > self::SNAP_METERS) {
            return null;
        }

        return $this->result($best, false, (int) round($bestDistance));
    }

    /**
     * Isi `district_code`, `village`, dan `village_id` laporan dari
     * koordinatnya. Tidak menyimpan; pemanggil yang menyimpan.
     *
     * Satu tempat untuk laporan baru dan backfill, supaya keduanya tidak
     * pernah mengisi dengan aturan yang berbeda.
     *
     * Titik yang tidak dapat ditentukan (tanpa koordinat, atau di luar
     * Ponorogo) mengosongkan ketiganya. Itu SAH: laporannya tetap diterima
     * dan tetap didisposisi, ia hanya tidak muncul di peta, dan itu lebih
     * jujur daripada menempatkannya di wilayah yang salah.
     *
     * @return array|null hasil locate(), atau null
     */
    public function applyTo(Report $report): ?array
    {
        $found = ($report->latitude !== null && $report->longitude !== null)
            ? $this->locate((float) $report->latitude, (float) $report->longitude)
            : null;

        $report->district_code = $found['district_code'] ?? null;

        // `village` (teks) diisi nama dari master, bukan dari geocoder:
        // batas BIG yang menentukan desa mana, dan nama di master adalah
        // ejaan resmi yang dipakai saringan panel.
        $village = $found ? Village::where('code', $found['village_code'])->first() : null;
        $report->village_id = $village?->id;
        $report->village = $village?->name;

        return $found;
    }

    protected function result(array $v, bool $inside, int $distance): array
    {
        return [
            'village_code' => $v['code'],
            'district_code' => $v['district_code'],
            'village_name' => $v['name'],
            'inside' => $inside,
            'distance_meters' => $distance,
        ];
    }

    /** Lintasan sinar (ray casting): di dalam cincin luar, di luar semua lubang. */
    protected function insideMultiPolygon(float $x, float $y, array $polygons): bool
    {
        foreach ($polygons as $rings) {
            if (! $this->insideRing($x, $y, $rings[0])) {
                continue;
            }

            for ($i = 1, $n = count($rings); $i < $n; $i++) {
                if ($this->insideRing($x, $y, $rings[$i])) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    protected function insideRing(float $x, float $y, array $ring): bool
    {
        $inside = false;
        $n = count($ring);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];

            if (($yi > $y) !== ($yj > $y)
                && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * Jarak terpendek (meter) dari titik ke garis batas.
     *
     * Proyeksi datar sekitar titik itu: pada jarak ratusan meter selisihnya
     * dengan haversine di bawah satu sentimeter, dan jauh lebih murah.
     */
    protected function distanceToEdges(float $x, float $y, array $polygons): float
    {
        $kx = 111320 * cos(deg2rad($y));
        $ky = 110574;
        $best = INF;

        foreach ($polygons as $rings) {
            foreach ($rings as $ring) {
                for ($i = 0, $n = count($ring) - 1; $i < $n; $i++) {
                    $ax = ($ring[$i][0] - $x) * $kx;
                    $ay = ($ring[$i][1] - $y) * $ky;
                    $bx = ($ring[$i + 1][0] - $x) * $kx;
                    $by = ($ring[$i + 1][1] - $y) * $ky;

                    $dx = $bx - $ax;
                    $dy = $by - $ay;
                    $len2 = $dx * $dx + $dy * $dy;
                    $t = $len2 > 0 ? max(0, min(1, -($ax * $dx + $ay * $dy) / $len2)) : 0;

                    $best = min($best, hypot($ax + $t * $dx, $ay + $t * $dy));
                }
            }
        }

        return $best;
    }

    /**
     * Dimuat sekali per proses. 1,4 MB, sekitar 20 ms untuk dibaca; worker
     * antrean dan proses PHP yang menerima laporan membayarnya sekali saja.
     */
    protected function villages(): array
    {
        return static::$villages ??= json_decode(
            file_get_contents(dirname(__DIR__, 2).'/database/boundaries/ponorogo-villages.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        )['villages'];
    }
}
