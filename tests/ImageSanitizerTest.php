<?php

namespace Nawasara\Aspirations\Tests;

use Nawasara\Aspirations\Support\ImageSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * Foto laporan disimpan tanpa EXIF GPS (audit keamanan 15 Oktober 2026,
 * temuan S11).
 *
 * Berkas uji dibangun di sini: JPEG dari GD, lalu disisipi segmen APP1 Exif
 * buatan tangan berisi Orientation dan GPSLatitudeRef. Cukup untuk
 * membuktikan dua hal yang penting: GPS hilang, dan orientasi diterapkan ke
 * piksel sebelum penandanya ikut hilang.
 */
class ImageSanitizerTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('exif_read_data')) {
            $this->markTestSkipped('Butuh ekstensi gd dan exif.');
        }

        $this->tmp = tempnam(sys_get_temp_dir(), 'img');
    }

    protected function tearDown(): void
    {
        @unlink($this->tmp);
    }

    /** JPEG 40x20 dengan EXIF Orientation + GPS. */
    private function jpegWithExif(int $orientation): string
    {
        $img = imagecreatetruecolor(40, 20);
        ob_start();
        imagejpeg($img);
        $jpeg = ob_get_clean();
        imagedestroy($img);

        // TIFF little-endian: IFD0 berisi Orientation (0x0112) dan penunjuk
        // GPS IFD (0x8825); GPS IFD berisi GPSLatitudeRef = "S".
        $tiff = 'II'.pack('v', 42).pack('V', 8);
        $tiff .= pack('v', 2);
        $tiff .= pack('vvV', 0x0112, 3, 1).pack('vv', $orientation, 0);
        $tiff .= pack('vvVV', 0x8825, 4, 1, 38);
        $tiff .= pack('V', 0);
        // offset 38: GPS IFD
        $tiff .= pack('v', 1);
        $tiff .= pack('vvV', 0x0001, 2, 2)."S\0\0\0";
        $tiff .= pack('V', 0);

        $payload = "Exif\0\0".$tiff;
        $app1 = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        // Sisipkan tepat setelah SOI (FFD8).
        return substr($jpeg, 0, 2).$app1.substr($jpeg, 2);
    }

    public function test_gps_hilang_dari_jpeg(): void
    {
        file_put_contents($this->tmp, $this->jpegWithExif(1));

        $before = @exif_read_data($this->tmp);
        $this->assertSame('S', $before['GPSLatitudeRef'] ?? null, 'data uji harus memuat GPS');

        $clean = ImageSanitizer::strip($this->tmp, 'image/jpeg');
        file_put_contents($this->tmp, $clean);

        $after = @exif_read_data($this->tmp);
        $this->assertArrayNotHasKey('GPSLatitudeRef', is_array($after) ? $after : []);
        $this->assertStringNotContainsString('Exif', $clean);
    }

    /** Orientasi 6 (putar 90 derajat): 40x20 harus menjadi 20x40. */
    public function test_orientasi_diterapkan_sebelum_dibuang(): void
    {
        file_put_contents($this->tmp, $this->jpegWithExif(6));

        $clean = ImageSanitizer::strip($this->tmp, 'image/jpeg');
        [$w, $h] = getimagesizefromstring($clean);

        $this->assertSame([20, 40], [$w, $h]);
    }

    public function test_png_tetap_png(): void
    {
        $img = imagecreatetruecolor(10, 10);
        imagepng($img, $this->tmp);
        imagedestroy($img);

        $clean = ImageSanitizer::strip($this->tmp, 'image/png');

        $this->assertSame("\x89PNG", substr($clean, 0, 4));
    }

    public function test_webp_potongan_exif_dan_xmp_dibuang(): void
    {
        $vp8x = 'VP8X'.pack('V', 10).chr(0x0C).str_repeat("\0", 9);
        $exif = 'EXIF'.pack('V', 5)."GPS!!\0";        // ganjil: diberi pengisi
        $xmp = 'XMP '.pack('V', 4).'<x/>';
        $vp8 = 'VP8 '.pack('V', 4).'abcd';
        $body = 'WEBP'.$vp8x.$exif.$xmp.$vp8;
        $webp = 'RIFF'.pack('V', strlen($body)).$body;

        $clean = ImageSanitizer::stripWebpChunks($webp);

        $this->assertStringNotContainsString('EXIF', $clean);
        $this->assertStringNotContainsString('GPS!!', $clean);
        $this->assertStringNotContainsString('XMP ', $clean);
        // Potongan gambar sendiri utuh.
        $this->assertStringEndsWith('VP8 '.pack('V', 4).'abcd', $clean);
        // Penanda EXIF/XMP di VP8X ikut dimatikan.
        $this->assertSame(0, ord($clean[20]) & 0x0C);
        // Ukuran RIFF ikut diperbarui.
        $this->assertSame(strlen($clean) - 8, unpack('V', substr($clean, 4, 4))[1]);
    }

    /** Ekstensi dari MIME hasil deteksi, bukan dari nama berkas klien. */
    public function test_ekstensi_dari_mime(): void
    {
        $this->assertSame('jpg', ImageSanitizer::extensionFor('image/jpeg'));
        $this->assertSame('webp', ImageSanitizer::extensionFor('image/webp'));
        $this->assertNull(ImageSanitizer::extensionFor('text/html'));
        $this->assertNull(ImageSanitizer::extensionFor('application/x-php'));
    }
}
