<?php

namespace Nawasara\Aspirations\Support;

/**
 * Nilai sel CSV yang aman dibuka di Excel / LibreOffice / Google Sheets.
 *
 * Audit keamanan 15 Oktober 2026, temuan R11 (CSV formula injection). Judul
 * laporan ditulis warga, dan ekspor dibuka staf OPD di spreadsheet. Sel yang
 * diawali `=`, `+`, `-`, `@`, tab, atau CR dibaca sebagai RUMUS: judul
 * `=HYPERLINK("http://...","Klik")` menjadi tautan, dan rumus DDE dapat
 * menjalankan perintah di komputer staf bila peringatannya diiyakan.
 *
 * Penangkalnya rekomendasi OWASP: awali sel seperti itu dengan tanda kutip
 * tunggal. Spreadsheet menampilkannya sebagai teks biasa; tanda kutipnya
 * terlihat di sel, dan itu harga yang wajar dibanding rumus yang berjalan.
 *
 * Angka TIDAK disentuh. Penilaian dan jumlah dukungan dikirim sebagai int, dan
 * angka negatif sah (bukan rumus) tetap harus terbaca sebagai angka.
 */
final class CsvCell
{
    /** Karakter awal yang membuat spreadsheet menafsirkan sel sebagai rumus. */
    public const DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public static function escape(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (in_array($value[0], self::DANGEROUS_PREFIXES, true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<int, mixed>
     */
    public static function escapeRow(array $row): array
    {
        return array_map(self::escape(...), $row);
    }
}
