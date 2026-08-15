<?php

namespace Nawasara\Aspirations\Support;

use Nawasara\Core\Models\Setting;

/**
 * Setelan yang dapat diubah ADMIN lewat panel, tanpa deploy.
 *
 * Membaca `nawasara_settings` lebih dulu, config sebagai cadangan. Config tetap
 * ada dan tetap berguna: ia nilai awal yang masuk akal sebelum admin pernah
 * menyentuh apa pun, dan penjaga bila baris settingnya terhapus.
 *
 * ── Kenapa ini ada ───────────────────────────────────────────────────────
 *
 * Angka-angka di bawah bukan setelan teknis, melainkan KEBIJAKAN:
 *
 *   batas laporan/hari  → seberapa longgar warga boleh melapor
 *   radius deteksi ganda → seberapa dekat dua laporan disebut sama
 *   batas tanggapan     → janji berapa lama warga menunggu jawaban
 *
 * Semuanya akan berubah setelah sistem dipakai dan Pemkab melihat perilakunya
 * yang sebenarnya. Mengharuskan deploy untuk mengubah janji ke warga berarti
 * perubahan itu tertunda berhari-hari — atau tidak pernah dilakukan.
 *
 * ⚠️ Kunci setting memakai awalan `aspirations.` supaya tidak bertabrakan
 * dengan paket lain yang berbagi tabel yang sama.
 */
class Settings
{
    /** Batas laporan per warga per hari (#12). */
    public static function reportsPerDay(): int
    {
        return self::int('reports_per_day', 'limits.reports_per_day', 5);
    }

    /** Foto per laporan warga. */
    public static function photosPerReport(): int
    {
        return self::int('photos_per_report', 'limits.photos_per_report', 3);
    }

    /** Ukuran maksimal satu foto, dalam KB. */
    public static function photoMaxKb(): int
    {
        return self::int('photo_max_kb', 'limits.photo_max_kb', 2048);
    }

    /** Panjang maksimal deskripsi laporan, dalam karakter. */
    public static function descriptionMax(): int
    {
        return self::int('description_max', 'limits.description_max', 500);
    }

    /** Radius deteksi laporan ganda, dalam meter (#11). */
    public static function duplicateRadius(): int
    {
        return self::int('duplicate_radius', 'duplicate.radius_meters', 50);
    }

    /** Rentang waktu deteksi ganda, dalam hari (#11). */
    public static function duplicateDays(): int
    {
        return self::int('duplicate_days', 'duplicate.window_days', 7);
    }

    /** Batas tanggapan pertama, dalam jam. Janji ke warga. */
    public static function responseHours(): int
    {
        return self::int('response_hours', 'sla.response_hours', 72);
    }

    /** Batas Kabid memverifikasi, dalam jam (#19). */
    public static function verificationHours(): int
    {
        return self::int('verification_hours', 'sla.verification_hours', 48);
    }

    /** Hari menunggu penilaian sebelum laporan ditutup otomatis (#7). */
    public static function autoCloseDays(): int
    {
        return self::int('auto_close_days', 'rating.auto_close_days', 7);
    }

    /** Nilai bintang yang membuka kembali laporan (#6). */
    public static function reopenThreshold(): int
    {
        return self::int('reopen_threshold', 'rating.reopen_threshold', 2);
    }

    /**
     * Semua setelan sekaligus — untuk halaman pengaturan di panel.
     *
     * @return array<string, int>
     */
    public static function all(): array
    {
        return [
            'reports_per_day' => self::reportsPerDay(),
            'photos_per_report' => self::photosPerReport(),
            'photo_max_kb' => self::photoMaxKb(),
            'description_max' => self::descriptionMax(),
            'duplicate_radius' => self::duplicateRadius(),
            'duplicate_days' => self::duplicateDays(),
            'response_hours' => self::responseHours(),
            'verification_hours' => self::verificationHours(),
            'auto_close_days' => self::autoCloseDays(),
            'reopen_threshold' => self::reopenThreshold(),
        ];
    }

    /** Simpan satu setelan. Cache dibersihkan sendiri oleh model Setting. */
    public static function put(string $key, int $value): void
    {
        Setting::set('aspirations.'.$key, (string) $value, 'string');
    }

    /**
     * Baca bilangan bulat: setting DB → config → bawaan.
     *
     * Nilai <= 0 dianggap TIDAK disetel dan jatuh ke config. Ini menutup salah
     * ketik yang berbahaya: admin yang tanpa sengaja menyimpan 0 pada
     * `duplicate_radius` akan mematikan deteksi ganda sepenuhnya tanpa ada
     * tanda apa pun.
     */
    protected static function int(string $key, string $configPath, int $default): int
    {
        $dbValue = (int) Setting::get('aspirations.'.$key, 0);

        if ($dbValue > 0) {
            return $dbValue;
        }

        return (int) config('nawasara-aspirations.'.$configPath, $default);
    }
}
