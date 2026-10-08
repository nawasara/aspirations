<?php

/*
 * Membangun `ponorogo-villages.json` dari unduhan batas desa BIG.
 *
 *   php database/boundaries/build.php <unduhan.json>
 *
 * Sumber: batas administrasi BIG pembaruan 13 Juni 2023, berkode Kemendagri,
 * diunduh per kabupaten dari https://batas-admin.geoit.dev:
 *
 *   https://server.geoit.dev/vendor/batas-admin/download.php?fid=35.02&column=kode_kk&table=kel_desa
 *
 * Dijalankan HANYA bila batas desa berubah (pemekaran, penataan ulang).
 * Hasilnya di-commit; aplikasi tidak pernah memanggil sumber di atas.
 *
 * Garis batas disederhanakan (Douglas-Peucker) dengan toleransi TOLERANCE
 * derajat. Data aslinya 187 ribu titik dan 6 MB, terlalu besar untuk dimuat
 * pada setiap laporan masuk, padahal sebagian besar titiknya hanya
 * berselisih beberapa sentimeter dari garis lurus di antaranya.
 */

const TOLERANCE = 0.00003; // ~3,3 m di lintang Ponorogo

if ($argc < 2) {
    fwrite(STDERR, "pakai: php build.php <unduhan.json>\n");
    exit(1);
}

$rows = json_decode(file_get_contents($argv[1]), true)['data'] ?? null;

if (! is_array($rows) || $rows === []) {
    fwrite(STDERR, "berkas tidak berisi `data`\n");
    exit(1);
}

/** @return array<int, array<int, array<int, array{0: float, 1: float}>>> poligon → cincin → titik [lng, lat] */
function parseMultiPolygon(string $wkt): array
{
    $body = trim(preg_replace('/^\s*MULTIPOLYGON\s*/i', '', $wkt));
    $polygons = [];

    // ((( ... ))) → poligon dipisah ")), ((" ; cincin dipisah "), ("
    foreach (preg_split('/\)\)\s*,\s*\(\(/', trim($body, '()')) as $polyText) {
        $rings = [];
        foreach (preg_split('/\)\s*,\s*\(/', $polyText) as $ringText) {
            $ring = [];
            foreach (explode(',', trim($ringText, '() ')) as $pt) {
                $p = preg_split('/\s+/', trim($pt));
                $ring[] = [(float) $p[0], (float) $p[1]];
            }
            $rings[] = $ring;
        }
        $polygons[] = $rings;
    }

    return $polygons;
}

/** Douglas-Peucker, iteratif supaya cincin panjang tidak menghabiskan tumpukan. */
function simplify(array $pts, float $tol): array
{
    $n = count($pts);
    if ($n < 5) {
        return $pts;
    }

    $keep = array_fill(0, $n, false);
    $keep[0] = $keep[$n - 1] = true;
    $stack = [[0, $n - 1]];

    while ($stack) {
        [$a, $b] = array_pop($stack);
        [$x1, $y1] = $pts[$a];
        [$x2, $y2] = $pts[$b];
        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        $len2 = $dx * $dx + $dy * $dy;

        $max = 0.0;
        $idx = -1;
        for ($i = $a + 1; $i < $b; $i++) {
            [$x, $y] = $pts[$i];
            if ($len2 == 0.0) {
                $d = hypot($x - $x1, $y - $y1);
            } else {
                $t = max(0, min(1, (($x - $x1) * $dx + ($y - $y1) * $dy) / $len2));
                $d = hypot($x - ($x1 + $t * $dx), $y - ($y1 + $t * $dy));
            }
            if ($d > $max) {
                $max = $d;
                $idx = $i;
            }
        }

        if ($max > $tol && $idx > 0) {
            $keep[$idx] = true;
            $stack[] = [$a, $idx];
            $stack[] = [$idx, $b];
        }
    }

    $out = [];
    foreach ($pts as $i => $p) {
        if ($keep[$i]) {
            $out[] = [round($p[0], 6), round($p[1], 6)];
        }
    }

    // Cincin yang menyusut di bawah segitiga tidak lagi punya luas; yang asli
    // dipakai supaya desa kecil tidak lenyap dari peta.
    return count($out) >= 4 ? $out : array_map(fn ($p) => [round($p[0], 6), round($p[1], 6)], $pts);
}

$villages = [];
$before = 0;
$after = 0;

foreach ($rows as $row) {
    $minLng = $minLat = INF;
    $maxLng = $maxLat = -INF;
    $polygons = [];

    foreach (parseMultiPolygon($row['WKT_GEOMETRY']) as $rings) {
        $poly = [];
        foreach ($rings as $ring) {
            $before += count($ring);
            $s = simplify($ring, TOLERANCE);
            $after += count($s);
            $poly[] = $s;
            foreach ($s as [$lng, $lat]) {
                $minLng = min($minLng, $lng);
                $maxLng = max($maxLng, $lng);
                $minLat = min($minLat, $lat);
                $maxLat = max($maxLat, $lat);
            }
        }
        $polygons[] = $poly;
    }

    $villages[] = [
        'code' => str_replace('.', '', $row['kode_kd']),
        'district_code' => str_replace('.', '', $row['kode_kec']),
        'name' => $row['kel_desa'],
        'bbox' => [$minLng, $minLat, $maxLng, $maxLat],
        'polygons' => $polygons,
    ];
}

usort($villages, fn ($a, $b) => strcmp($a['code'], $b['code']));

$out = __DIR__.'/ponorogo-villages.json';
file_put_contents($out, json_encode([
    'source' => 'BIG, batas administrasi pembaruan 13 Juni 2023, kode Kemendagri',
    'tolerance_degrees' => TOLERANCE,
    'villages' => $villages,
], JSON_UNESCAPED_UNICODE));

printf("%d desa, titik %d → %d, %s KB\n", count($villages), $before, $after, number_format(filesize($out) / 1024));
