<?php

namespace Nawasara\Aspirations\Support;

use Carbon\CarbonInterface;

/**
 * Kapan sebuah tenggat layak diperingatkan. Fungsi murni, tanpa basis data,
 * supaya batasnya dapat diuji persis.
 */
final class SlaReminderPlan
{
    /** Peringatan paling awal: sehari sebelum tenggat. */
    public const MAX_WINDOW_HOURS = 24;

    /**
     * Peringatan paling akhir: sejam sebelum tenggat.
     *
     * Job berjalan tiap jam. Jendela yang lebih sempit dari sejam dapat jatuh
     * di antara dua putaran, dan peringatannya tidak pernah terkirim.
     */
    public const MIN_WINDOW_HOURS = 1;

    /** Bagian dari rentang SLA yang tersisa saat peringatan dikirim. */
    public const WINDOW_SHARE = 0.25;

    /**
     * Panjang jendela peringatan, dalam jam.
     *
     * Proporsional, bukan angka tetap. "24 jam sebelumnya" pada SLA 3 jam
     * berarti peringatan datang sebelum laporannya masuk; "1 jam sebelumnya"
     * pada SLA 14 hari terlalu mepet untuk mengerjakan apa pun. Seperempat
     * rentang, dibatasi 1 sampai 24 jam.
     */
    public static function windowHours(CarbonInterface $start, CarbonInterface $due): float
    {
        $span = max(0, $start->diffInMinutes($due, false)) / 60;

        return min(self::MAX_WINDOW_HOURS, max(self::MIN_WINDOW_HOURS, $span * self::WINDOW_SHARE));
    }

    /**
     * Tenggat ini perlu diperingatkan SEKARANG?
     *
     * Hanya sebelum tenggat. Setelah lewat, yang berlaku pemberitahuan
     * "sudah lewat", bukan peringatan.
     */
    public static function shouldWarn(
        ?CarbonInterface $start,
        ?CarbonInterface $due,
        bool $done,
        bool $alreadyWarned,
        CarbonInterface $now,
    ): bool {
        if ($done || $alreadyWarned || $start === null || $due === null || ! $due->greaterThan($now)) {
            return false;
        }

        $remainingHours = $now->diffInMinutes($due, false) / 60;

        return $remainingHours <= self::windowHours($start, $due);
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
