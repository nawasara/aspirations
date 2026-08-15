<?php

namespace Nawasara\Aspirations\Support;

use Illuminate\Support\Facades\DB;
use Nawasara\Aspirations\Models\Report;

/**
 * Kode laporan yang dilihat warga: LB-2026-08-0412
 *
 * Urut PER BULAN, bukan global — angka yang terus membesar sepanjang tahun
 * akan membocorkan total laporan kabupaten kepada siapa pun yang melihat satu
 * kode, dan angka kecil di awal tahun terlihat seperti sistem sepi.
 */
class ReportCode
{
    public const PREFIX = 'LB';

    /**
     * Terbitkan kode berikutnya.
     *
     * ⚠️ Dipanggil DI DALAM transaksi bersama penyimpanan laporan. Dua warga
     * yang mengirim pada detik yang sama akan menghitung urutan yang sama bila
     * dipanggil di luar transaksi, lalu satu di antaranya gagal karena kode
     * unik — dan yang gagal itu laporan warga yang sudah terlanjur menunggu.
     */
    public static function next(?\DateTimeInterface $at = null): string
    {
        $at = $at ?: now();
        $year = $at->format('Y');
        $month = $at->format('m');
        $prefix = sprintf('%s-%s-%s-', self::PREFIX, $year, $month);

        $last = Report::query()
            ->where('code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('code')
            ->value('code');

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
