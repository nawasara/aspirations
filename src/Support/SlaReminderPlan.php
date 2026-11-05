<?php

namespace Nawasara\Aspirations\Support;

use Carbon\CarbonInterface;

/**
 * Kapan sebuah tenggat layak diperingatkan. Fungsi murni, tanpa basis data,
 * supaya batasnya dapat diuji persis.
 *
 * Angka bawaannya di konstanta di bawah; yang berlaku dibaca dari config
 * `nawasara-aspirations.sla_reminders` lewat self::limitsFrom().
 */
final class SlaReminderPlan
{
    /** Peringatan paling awal: sehari sebelum tenggat. */
    public const MAX_WINDOW_HOURS = 24;

    /**
     * Peringatan paling akhir: sejam sebelum tenggat.
     *
     * Job berjalan tiap jam. Jendela yang lebih sempit dari sejam dapat jatuh
     * di antara dua putaran, dan peringatannya tidak pernah terkirim. Karena
     * itu angka dari config pun tidak dapat menurunkannya di bawah ini.
     */
    public const MIN_WINDOW_HOURS = 1;

    /** Bagian dari rentang SLA yang tersisa saat peringatan dikirim. */
    public const WINDOW_SHARE = 0.25;

    /**
     * Batas jendela dari config, dijaga supaya angka keliru tidak mematikan
     * peringatan diam-diam.
     *
     *   share  0 < share <= 1   di luar itu kembali ke bawaan
     *   min    >= 1 jam          lebih kecil dapat terlewat jadwal per jam
     *   max    >= min
     *
     * @param  array<string, mixed>  $config  isi `sla_reminders`
     * @return array{share: float, min: float, max: float}
     */
    public static function limitsFrom(array $config): array
    {
        $share = (float) ($config['window_share'] ?? self::WINDOW_SHARE);
        if ($share <= 0 || $share > 1) {
            $share = self::WINDOW_SHARE;
        }

        $min = (float) max(self::MIN_WINDOW_HOURS, (float) ($config['min_window_hours'] ?? self::MIN_WINDOW_HOURS));
        $max = (float) max($min, (float) ($config['max_window_hours'] ?? self::MAX_WINDOW_HOURS));

        return ['share' => $share, 'min' => $min, 'max' => $max];
    }

    /** @return array{share: float, min: float, max: float} */
    public static function defaultLimits(): array
    {
        return self::limitsFrom([]);
    }

    /**
     * Panjang jendela peringatan, dalam jam.
     *
     * Proporsional, bukan angka tetap. "24 jam sebelumnya" pada SLA 3 jam
     * berarti peringatan datang sebelum laporannya masuk; "1 jam sebelumnya"
     * pada SLA 14 hari terlalu mepet untuk mengerjakan apa pun. Bawaannya
     * seperempat rentang, dibatasi 1 sampai 24 jam.
     *
     * @param  array{share: float, min: float, max: float}|null  $limits
     */
    public static function windowHours(CarbonInterface $start, CarbonInterface $due, ?array $limits = null): float
    {
        $limits ??= self::defaultLimits();
        $span = max(0, $start->diffInMinutes($due, false)) / 60;

        return (float) min($limits['max'], max($limits['min'], $span * $limits['share']));
    }

    /**
     * Tenggat ini perlu diperingatkan SEKARANG?
     *
     * Hanya sebelum tenggat. Setelah lewat, yang berlaku pemberitahuan
     * "sudah lewat", bukan peringatan.
     *
     * @param  array{share: float, min: float, max: float}|null  $limits
     */
    public static function shouldWarn(
        ?CarbonInterface $start,
        ?CarbonInterface $due,
        bool $done,
        bool $alreadyWarned,
        CarbonInterface $now,
        ?array $limits = null,
    ): bool {
        if ($done || $alreadyWarned || $start === null || $due === null || ! $due->greaterThan($now)) {
            return false;
        }

        $remainingHours = $now->diffInMinutes($due, false) / 60;

        return $remainingHours <= self::windowHours($start, $due, $limits);
    }

    /** "5 jam", "1 hari 3 jam", "40 menit": dibaca di ponsel, sering terburu-buru. */
    public static function humanRemaining(CarbonInterface $now, CarbonInterface $due): string
    {
        $minutes = (int) max(0, $now->diffInMinutes($due, false));

        if ($minutes < 60) {
            return $minutes.' menit';
        }

        $hours = intdiv($minutes, 60);
        if ($hours < 24) {
            return $hours.' jam';
        }

        $days = intdiv($hours, 24);
        $rest = $hours % 24;

        return $days.' hari'.($rest > 0 ? " {$rest} jam" : '');
    }
}
