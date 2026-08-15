<?php

namespace Nawasara\Aspirations\Services;

use Illuminate\Support\Str;

/**
 * Saringan kata kasar & ujaran kebencian (#20).
 *
 * Dulu dikerjakan Admin Kabupaten pada tahap yang dihapus keputusan D1. Tanpa
 * penggantinya, laporan berisi ujaran kebencian mendarat LANGSUNG di panel OPD
 * — dan bila panel itu suatu saat dibuka lebih luas, tampil ke lebih banyak
 * orang lagi.
 *
 * ── Yang dilakukan, dan yang TIDAK ──────────────────────────────────────
 *
 * Ini MENANDAI, bukan menolak. Alasannya penting: laporan warga yang marah
 * sering memakai kata kasar, dan kemarahan itu sendiri sah — parkir liar yang
 * dibiarkan bertahun-tahun memang membuat orang jengkel. Menolak laporannya
 * berarti menghukum warga karena nada bicaranya, lalu kehilangan isinya.
 *
 * Yang ditandai masuk antrean tinjauan; yang bersih langsung ke OPD.
 *
 * ⚠️ Daftar katanya SENGAJA pendek dan konservatif. Penyaring yang terlalu
 * galak lebih berbahaya daripada tidak ada: kalau "anjing" ditolak mentah,
 * laporan sah tentang anjing liar yang menggigit anak ikut tertolak — dan
 * petugas belajar bahwa penandanya tidak dapat dipercaya, lalu mengabaikan
 * semuanya.
 */
class ContentFilter
{
    /**
     * Kata yang hampir selalu bermuatan penghinaan atau SARA.
     *
     * Tidak memuat kata yang punya makna sehari-hari yang sah ("anjing",
     * "monyet" — keduanya hewan yang wajar dilaporkan warga). Menyaringnya
     * akan menghasilkan lebih banyak salah-tandai daripada tangkapan benar.
     */
    protected const KASAR = [
        // Umpatan yang tidak punya makna lain
        'bangsat', 'kontol', 'memek', 'ngentot', 'jancok', 'jancuk',
        'asu', 'bajingan', 'keparat', 'brengsek', 'tolol', 'goblok',
        'idiot', 'bego', 'sinting',
    ];

    /**
     * Penanda ujaran kebencian berbasis identitas.
     *
     * Dipisah dari umpatan karena bobotnya berbeda: umpatan menandakan warga
     * yang marah, ini menandakan sesuatu yang tidak boleh tampil di kanal
     * pemerintah sama sekali.
     */
    protected const SARA = [
        'cina', 'cino', 'kafir', 'kapir', 'pribumi', 'aseng',
    ];

    /**
     * Periksa teks laporan.
     *
     * @return array{flagged: bool, reasons: array<int, string>, terms: array<int, string>}
     */
    public function check(string ...$texts): array
    {
        $raw = implode(' ', $texts);
        $combined = Str::lower($raw);

        $reasons = [];
        $words = [];

        $profanityHits = $this->matchWords($combined, self::KASAR);
        if ($profanityHits !== []) {
            $reasons[] = 'kata_kasar';
            $words = array_merge($words, $profanityHits);
        }

        $slurHits = $this->matchWords($combined, self::SARA);
        if ($slurHits !== []) {
            $reasons[] = 'sara';
            $words = array_merge($words, $slurHits);
        }

        if ($this->isShouting($raw)) {
            $reasons[] = 'huruf_kapital_berlebih';
        }

        return [
            'flagged' => $reasons !== [],
            'reasons' => $reasons,
            'terms' => array_values(array_unique($words)),
        ];
    }

    /**
     * Cari kata dalam teks.
     *
     * Dicocokkan sebagai KATA UTUH, bukan potongan. Tanpa batas kata,
     * "bangsat" akan cocok di dalam kata lain dan — yang lebih sering terjadi —
     * kata pendek akan cocok di mana-mana. Ini penyebab umum penyaring yang
     * menandai segalanya lalu diabaikan orang.
     */
    protected function matchWords(string $text, array $list): array
    {
        $found = [];

        foreach ($list as $words) {
            if (preg_match('/\b'.preg_quote($words, '/').'\b/u', $text)) {
                $found[] = $words;
            }
        }

        return $found;
    }

    /**
     * Teks yang sebagian besar huruf kapital.
     *
     * Bukan pelanggaran, tetapi tanda laporan yang ditulis dalam kemarahan —
     * berguna bagi petugas untuk menyiapkan nada jawabannya, bukan untuk
     * menolak laporannya.
     *
     * Hanya berlaku pada teks yang cukup panjang: judul pendek berhuruf
     * kapital adalah hal biasa dan bukan apa-apa.
     */
    protected function isShouting(string $raw): bool
    {
        $letters = preg_replace('/[^a-zA-Z]/', '', $raw);

        if (strlen($letters) < 30) {
            return false;
        }

        $uppercase = preg_replace('/[^A-Z]/', '', $letters);

        // 80%: menyisakan ruang untuk singkatan yang wajar (RT, RW, OPD, PKL)
        // di dalam kalimat yang ditulis normal.
        return strlen($uppercase) / strlen($letters) > 0.8;
    }
}
