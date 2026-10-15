<?php

namespace Nawasara\Aspirations\Support;

use Illuminate\Cache\RateLimiting\Limit;

/**
 * Batas laju khusus untuk endpoint yang membaca laporan warga LAIN:
 * `GET /reports/similar` dan `GET /reports/{code}/public`.
 *
 * Audit keamanan 15 Oktober 2026, temuan T5. Batas umum
 * `throttle:nawasara-citizen` (60/menit per akun) cukup untuk pemakaian biasa
 * tetapi longgar untuk dua endpoint ini: `similar` menerima titik sembarang
 * sehingga dapat dipakai menyapu wilayah, dan kode laporan berurutan
 * (LB-2026-10-0001, 0002, ...) dapat ditebak satu per satu. Akun warga gratis
 * (cukup login Google), jadi batas per akun saja dapat dielakkan dengan banyak
 * akun; karena itu ada juga batas harian per IP yang lebih longgar.
 *
 * Tiap Limit diberi kunci `by` yang BERBEDA. Laravel menghitung beberapa Limit
 * dengan kunci yang sama sebagai satu penghitung, sehingga batas menit dan
 * batas hari akan saling menimpa.
 */
final class CitizenReadLimits
{
    /**
     * @return list<Limit>
     */
    public static function build(
        string $name,
        ?string $sub,
        string $ip,
        int $perMinute,
        int $perDay,
        int $ipPerDay,
    ): array {
        // Tanpa `sub` (seharusnya tidak terjadi di belakang api.citizen) jatuh
        // ke IP, supaya batasnya tidak hilang sama sekali.
        $who = ($sub !== null && $sub !== '') ? 'sub:'.$sub : 'ip:'.$ip;
        $limits = [];

        if ($perMinute > 0) {
            $limits[] = Limit::perMinute($perMinute)->by("{$name}:min:{$who}");
        }

        if ($perDay > 0) {
            $limits[] = Limit::perDay($perDay)->by("{$name}:day:{$who}");
        }

        if ($ipPerDay > 0) {
            $limits[] = Limit::perDay($ipPerDay)->by("{$name}:ipday:{$ip}");
        }

        foreach ($limits as $limit) {
            $limit->response(fn ($request, array $headers) => response()->json([
                'error' => [
                    'code' => 'too_many_requests',
                    'message' => self::message((int) ($headers['Retry-After'] ?? 60)),
                ],
            ], 429, $headers));
        }

        return $limits;
    }

    /** Pesan 429 untuk dibaca warga, dengan waktu tunggu yang dibulatkan. */
    public static function message(int $retryAfterSeconds): string
    {
        return 'Terlalu banyak permintaan laporan warga lain. Coba lagi dalam '
            .self::humanWait($retryAfterSeconds).'.';
    }

    /** "45 detik", "3 menit", "2 jam". Dibulatkan ke atas supaya tidak terlalu cepat. */
    public static function humanWait(int $seconds): string
    {
        $seconds = max(1, $seconds);

        if ($seconds < 60) {
            return "{$seconds} detik";
        }

        if ($seconds < 3600) {
            return ((int) ceil($seconds / 60)).' menit';
        }

        return ((int) ceil($seconds / 3600)).' jam';
    }
}
