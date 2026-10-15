<?php

namespace Nawasara\Aspirations\Support;

use RuntimeException;

/**
 * Buang metadata (EXIF, GPS, XMP) dari foto sebelum disimpan.
 *
 * Audit keamanan 15 Oktober 2026, temuan S11. Foto laporan dulu disimpan
 * persis seperti diunggah, lengkap dengan EXIF GPS, lalu disajikan di feed
 * dan detail publik. Siapa pun yang mengunduh fotonya mendapat koordinat
 * persis tempat warga berdiri, sehingga kebijakan "tanpa koordinat" pada
 * resource publik tidak berarti apa-apa.
 *
 * EXIF tetap DIBACA lebih dulu oleh PhotoUploader dan disimpan ke kolom
 * `captured_*` (berguna bagi verifikator). Yang dibuang hanya salinan di dalam
 * berkas yang dapat diunduh.
 *
 * Caranya per jenis:
 *
 *   JPEG, PNG  di-encode ulang dengan GD. Encoder GD tidak menulis metadata
 *              apa pun, jadi apa pun yang ada di berkas asal (termasuk segmen
 *              yang tidak kita kenal) ikut hilang. Lebih aman daripada
 *              membuang segmen satu per satu.
 *   WebP       di-encode ulang bila GD dibangun dengan WebP. Image produksi
 *              TIDAK (Dockerfile: `--with-freetype --with-jpeg` saja), jadi
 *              jalur cadangannya membuang potongan `EXIF` dan `XMP ` dari
 *              wadah RIFF secara langsung. Piksel tidak disentuh.
 *
 * Orientasi EXIF diterapkan ke piksel SEBELUM metadata dibuang. Tanpa itu foto
 * potret dari ponsel (yang menyimpan piksel mendatar lalu menandainya
 * "putar 90°") tampil rebah setelah penandanya hilang.
 */
class ImageSanitizer
{
    /**
     * Batas piksel yang mau didekode, sekitar 20 MP.
     *
     * Ukuran berkas sudah dibatasi (2 MB bawaan), tetapi PNG 2 MB dapat
     * berukuran 20.000 x 20.000 piksel: GD butuh 4 byte per piksel, dan
     * rotasi membuat salinan kedua. Dengan memory_limit 256M, 20 MP (sekitar
     * 80 MB per salinan) masih aman; kamera ponsel biasa 12 MP.
     */
    public const MAX_PIXELS = 20_000_000;

    /** Ekstensi berkas tersimpan, dari MIME HASIL DETEKSI isi berkas. */
    public const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Ekstensi untuk MIME hasil deteksi, atau null bila jenisnya tidak
     * diterima.
     *
     * Bukan dari nama berkas kiriman klien (audit 15 Oktober 2026, R/13 daftar
     * injeksi): nama itu sepenuhnya dikendalikan pemanggil, dan `foto.php`
     * atau `foto.html` berisi JPEG akan tersimpan dengan ekstensi yang
     * menentukan cara bucket menyajikannya.
     */
    public static function extensionFor(string $mime): ?string
    {
        return self::EXTENSIONS[$mime] ?? null;
    }

    /**
     * Kembalikan isi berkas tanpa metadata.
     *
     * @throws RuntimeException bila berkas tidak dapat diproses. Pemanggil
     *         WAJIB menolak unggahan, bukan menyimpan berkas asal: menyimpan
     *         aslinya berarti menyimpan GPS-nya.
     */
    public static function strip(string $path, string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => self::reencodeJpeg($path),
            'image/png' => self::reencodePng($path),
            'image/webp' => self::gdSupportsWebp()
                ? self::reencodeWebp($path)
                : self::stripWebpChunks((string) file_get_contents($path)),
            default => throw new RuntimeException("Jenis gambar tidak didukung: {$mime}"),
        };
    }

    protected static function reencodeJpeg(string $path): string
    {
        self::assertDecodable($path);

        $image = @imagecreatefromjpeg($path);

        if ($image === false) {
            throw new RuntimeException('JPEG tidak dapat dibaca.');
        }

        $image = self::applyOrientation($image, self::exifOrientation($path));

        try {
            return self::capture(fn () => imagejpeg($image, null, 85));
        } finally {
            imagedestroy($image);
        }
    }

    protected static function reencodePng(string $path): string
    {
        self::assertDecodable($path);

        $image = @imagecreatefrompng($path);

        if ($image === false) {
            throw new RuntimeException('PNG tidak dapat dibaca.');
        }

        // Transparansi dipertahankan; tanpa dua baris ini latar transparan
        // berubah hitam.
        imagealphablending($image, false);
        imagesavealpha($image, true);

        try {
            return self::capture(fn () => imagepng($image, null, 6));
        } finally {
            imagedestroy($image);
        }
    }

    protected static function reencodeWebp(string $path): string
    {
        self::assertDecodable($path);

        $image = @imagecreatefromwebp($path);

        if ($image === false) {
            throw new RuntimeException('WebP tidak dapat dibaca.');
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        try {
            return self::capture(fn () => imagewebp($image, null, 85));
        } finally {
            imagedestroy($image);
        }
    }

    protected static function gdSupportsWebp(): bool
    {
        return function_exists('imagecreatefromwebp')
            && function_exists('gd_info')
            && (gd_info()['WebP Support'] ?? false);
    }

    /**
     * Buang potongan `EXIF` dan `XMP ` dari wadah RIFF WebP.
     *
     * Jalur cadangan bila GD tidak dapat mendekode WebP. Spesifikasi wadah
     * WebP: "RIFF" + ukuran (LE32) + "WEBP", lalu potongan berformat FourCC +
     * ukuran (LE32) + isi, diberi satu byte pengisi bila ukurannya ganjil.
     * Penanda keberadaan EXIF/XMP ada di byte pertama isi `VP8X` dan ikut
     * dimatikan, kalau tidak pembaca yang taat spesifikasi akan mencari
     * potongan yang sudah tidak ada.
     *
     * Orientasi tidak perlu diterapkan: spesifikasi WebP sendiri menyatakan
     * Orientation di EXIF diabaikan saat menampilkan.
     */
    public static function stripWebpChunks(string $bytes): string
    {
        if (strlen($bytes) < 12 || substr($bytes, 0, 4) !== 'RIFF' || substr($bytes, 8, 4) !== 'WEBP') {
            throw new RuntimeException('Wadah WebP tidak sah.');
        }

        $out = '';
        $offset = 12;
        $length = strlen($bytes);

        while ($offset + 8 <= $length) {
            $fourcc = substr($bytes, $offset, 4);
            $size = unpack('V', substr($bytes, $offset + 4, 4))[1];
            $padded = $size + ($size & 1);

            if ($offset + 8 + $size > $length) {
                throw new RuntimeException('Potongan WebP terpotong.');
            }

            $chunk = substr($bytes, $offset, 8 + $padded);

            if ($fourcc === 'VP8X' && $size >= 1) {
                // Bit 3 (0x08) = EXIF, bit 2 (0x04) = XMP.
                $chunk[8] = chr(ord($chunk[8]) & ~0x0C);
            }

            if ($fourcc !== 'EXIF' && $fourcc !== 'XMP ') {
                $out .= $chunk;
            }

            $offset += 8 + $padded;
        }

        return 'RIFF'.pack('V', 4 + strlen($out)).'WEBP'.$out;
    }

    /**
     * Terapkan orientasi EXIF ke piksel.
     *
     * Nilai 1..8 menurut spesifikasi EXIF. `imagerotate` memutar BERLAWANAN
     * arah jarum jam untuk sudut positif, jadi "putar 90° searah jarum jam"
     * (nilai 6) ditulis -90.
     *
     * @param  \GdImage  $image
     * @return \GdImage
     */
    public static function applyOrientation($image, int $orientation)
    {
        $rotate = fn (int $angle) => self::replace($image, imagerotate($image, $angle, 0));

        switch ($orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 3:
                $image = $rotate(180);
                break;
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);
                break;
            case 5:
                // Transpose: putar 90° searah jarum jam, lalu cermin mendatar.
                $image = $rotate(-90);
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 6:
                $image = $rotate(-90);
                break;
            case 7:
                // Transverse: putar 90° berlawanan arah jarum jam, lalu cermin.
                $image = $rotate(90);
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 8:
                $image = $rotate(90);
                break;
        }

        return $image;
    }

    /**
     * Ganti gambar lama dengan hasil rotasi, dan bebaskan yang lama segera
     * supaya dua salinan besar tidak bertahan lebih lama dari perlu.
     */
    protected static function replace($old, $new)
    {
        if ($new === false) {
            throw new RuntimeException('Gagal memutar gambar.');
        }

        imagedestroy($old);

        return $new;
    }

    /** Orientasi dari EXIF, 1 (apa adanya) bila tidak ada atau tak terbaca. */
    protected static function exifOrientation(string $path): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }

        try {
            $data = @exif_read_data($path);
        } catch (\Throwable) {
            return 1;
        }

        $value = is_array($data) ? (int) ($data['Orientation'] ?? 1) : 1;

        return $value >= 1 && $value <= 8 ? $value : 1;
    }

    /** Tolak gambar yang dimensinya akan menghabiskan memori saat didekode. */
    protected static function assertDecodable(string $path): void
    {
        $size = @getimagesize($path);

        if ($size === false) {
            throw new RuntimeException('Dimensi gambar tidak terbaca.');
        }

        if ((int) $size[0] * (int) $size[1] > self::MAX_PIXELS) {
            throw new RuntimeException('Dimensi gambar terlalu besar.');
        }
    }

    protected static function capture(callable $encode): string
    {
        ob_start();

        try {
            $ok = $encode();
        } finally {
            $bytes = (string) ob_get_clean();
        }

        if ($ok === false || $bytes === '') {
            throw new RuntimeException('Gagal menyandikan ulang gambar.');
        }

        return $bytes;
    }
}
