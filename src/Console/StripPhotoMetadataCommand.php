<?php

namespace Nawasara\Aspirations\Console;

use Illuminate\Console\Command;
use Nawasara\Aspirations\Models\Attachment;
use Nawasara\Aspirations\Support\ImageSanitizer;

/**
 * Buang metadata (EXIF GPS) dari foto yang SUDAH tersimpan.
 *
 * Audit keamanan 15 Oktober 2026, temuan S11. PhotoUploader kini menyimpan
 * salinan tanpa metadata, tetapi itu hanya berlaku untuk unggahan baru. Foto
 * yang masuk sebelumnya masih membawa GPS dan tetap disajikan di feed dan
 * detail publik, jadi perlu disapu sekali.
 *
 * Kolom `captured_*` tidak disentuh: nilainya sudah dibaca saat unggah dan
 * tetap dipakai verifikator.
 *
 * Aman dijalankan berulang: berkas yang sudah bersih ditulis ulang dengan isi
 * yang setara (encoder GD tidak menambah metadata).
 */
class StripPhotoMetadataCommand extends Command
{
    protected $signature = 'aspirations:strip-photo-metadata
                            {--dry-run : Hanya hitung, tanpa menulis ulang berkas}';

    protected $description = 'Buang EXIF/GPS dari foto laporan yang sudah tersimpan';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $done = 0;
        $failed = 0;

        Attachment::query()->orderBy('id')->chunkById(100, function ($rows) use ($dry, &$done, &$failed) {
            foreach ($rows as $attachment) {
                /** @var Attachment $attachment */
                $tmp = tempnam(sys_get_temp_dir(), 'asp');

                try {
                    $disk = $attachment->storageDisk();
                    file_put_contents($tmp, $disk->get($attachment->path));

                    // MIME dari isi berkas, bukan kolom `mime`: kolom itu
                    // sudah dari finfo saat unggah, tetapi isi berkaslah yang
                    // menentukan cara ia didekode.
                    $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
                    $clean = ImageSanitizer::strip($tmp, $mime);

                    if (! $dry) {
                        $disk->put($attachment->path, $clean);
                        $attachment->forceFill([
                            'size' => strlen($clean),
                            'checksum_sha256' => hash('sha256', $clean),
                        ])->save();
                    }

                    $done++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->warn("Lewati {$attachment->getKey()} ({$attachment->path}): {$e->getMessage()}");
                } finally {
                    @unlink($tmp);
                }
            }
        });

        $this->info(($dry ? '[dry-run] ' : '')."Dibersihkan: {$done}, gagal: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
