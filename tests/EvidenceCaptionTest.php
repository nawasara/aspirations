<?php

namespace Nawasara\Aspirations\Tests;

use Illuminate\Http\Request;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Nawasara\Aspirations\Http\Api\StaffReportController;
use Nawasara\Aspirations\Http\Resources\AttachmentResource;
use Nawasara\Aspirations\Http\Resources\StaffAttachmentResource;
use Nawasara\Aspirations\Models\Attachment;
use Nawasara\Aspirations\Services\PhotoUploader;
use PHPUnit\Framework\TestCase;

/**
 * Keterangan foto bukti: sebelum ini dibuang Laravel tanpa galat.
 *
 * Petugas melihat "foto tersimpan" dan kalimatnya lenyap. Yang diuji adalah
 * tiga titik yang dulu memutusnya: aturan validasi, penyimpanan, dan jawaban.
 */
class EvidenceCaptionTest extends TestCase
{
    /** Hanya aturan teks; `photo` butuh berkas sungguhan. */
    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $rules = array_intersect_key(StaffReportController::evidenceRules(), ['caption' => 1, 'source' => 1]);

        return (new Factory(new Translator(new ArrayLoader, 'id')))
            ->make($data, $rules, StaffReportController::evidenceMessages());
    }

    public function test_aturan_mengenal_caption(): void
    {
        $this->assertArrayHasKey('caption', StaffReportController::evidenceRules());
    }

    public function test_tanpa_caption_tetap_diterima(): void
    {
        $this->assertFalse($this->validate(['source' => 'gallery'])->fails());
        $this->assertFalse($this->validate(['caption' => null])->fails());
    }

    public function test_caption_500_karakter_diterima_501_ditolak(): void
    {
        $this->assertFalse($this->validate(['caption' => str_repeat('a', 500)])->fails());

        $v = $this->validate(['caption' => str_repeat('a', 501)]);
        $this->assertTrue($v->fails());
        $this->assertSame('Keterangan foto paling banyak 500 karakter.', $v->errors()->first('caption'));
    }

    public function test_caption_kosong_disimpan_null(): void
    {
        $this->assertNull(PhotoUploader::normalizeCaption(null));
        $this->assertNull(PhotoUploader::normalizeCaption('   '));
        $this->assertSame('Pengaspalan selesai 24 Oktober', PhotoUploader::normalizeCaption('  Pengaspalan selesai 24 Oktober '));
    }

    private function attachment(?string $caption): Attachment
    {
        // temporaryUrl() butuh disk dan URL bertanda tangan; yang diuji di sini
        // hanya bentuk jawabannya.
        $a = new class extends Attachment
        {
            public function temporaryUrl(): ?string
            {
                return 'https://contoh.test/foto';
            }

            public function isSuspect(): bool
            {
                return false;
            }
        };

        $a->setRawAttributes([
            'kind' => Attachment::KIND_EVIDENCE,
            'source' => 'gallery',
            'path' => 'aspirations/rahasia.jpg',
            'caption' => $caption,
        ]);

        return $a;
    }

    public function test_jawaban_warga_dan_petugas_memuat_caption(): void
    {
        $a = $this->attachment('Pengaspalan selesai, 40 meter');

        $this->assertSame('Pengaspalan selesai, 40 meter', (new AttachmentResource($a))->resolve(new Request)['caption']);
        $this->assertSame('Pengaspalan selesai, 40 meter', (new StaffAttachmentResource($a))->resolve(new Request)['caption']);
    }

    public function test_foto_lama_tanpa_caption_tetap_berbentuk_sama(): void
    {
        $data = (new AttachmentResource($this->attachment(null)))->resolve(new Request);

        $this->assertArrayHasKey('caption', $data);
        $this->assertNull($data['caption']);
        // Daftar-izin tetap: kunci objek tidak ikut.
        $this->assertArrayNotHasKey('path', $data);
    }
}
