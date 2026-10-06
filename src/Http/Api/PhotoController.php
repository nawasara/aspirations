<?php

namespace Nawasara\Aspirations\Http\Api;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Nawasara\Aspirations\Models\Attachment;
use Nawasara\Vault\Services\MinioDisk;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mengalirkan foto laporan dari MinIO ke web dan aplikasi.
 *
 * Kenapa lewat Nawasara, bukan presigned URL langsung ke MinIO (Oktober 2026):
 * MinIO tidak punya alamat publik yang bisa melayaninya. Server bersama ada di
 * jaringan internal (`111.1.1.53`), presigned URL yang menunjuk ke sana tidak
 * terjangkau dari ponsel warga dan ditolak peramban sebagai mixed content.
 * Satu-satunya nama publik, `obs.ponorogo.go.id`, menunjuk ke konsol dan
 * dilindungi Cloudflare Access; pengecualian untuk jalur foto yang pernah
 * dibuat sempat hilang tanpa ada yang tahu, dan foto berhenti tampil.
 *
 * Mengalirkannya lewat Nawasara berarti MinIO tidak perlu dibuka ke internet
 * sama sekali, dan tidak bergantung pada aturan di luar Nawasara.
 *
 * Akses dijaga oleh TANDA TANGAN URL yang kedaluwarsa, sama seperti presigned
 * URL MinIO, bukan oleh login: tag <img> di web tidak dapat mengirim header
 * token. Yang ditandatangani hanya jalurnya (relatif), supaya URL tetap sah
 * di belakang Cloudflare dan nginx yang dapat mengubah host atau skema.
 */
class PhotoController extends Controller
{
    public function __invoke(Attachment $attachment): Response
    {
        try {
            $disk = ($attachment->disk === 'minio' && $attachment->bucket && class_exists(MinioDisk::class))
                ? MinioDisk::make($attachment->bucket)
                : Storage::disk($attachment->disk);

            $stream = $disk->readStream($attachment->path);
        } catch (\Throwable $e) {
            report($e);
            $stream = null;
        }

        if (! is_resource($stream)) {
            // 404 tanpa rincian: jangan memberi tahu apakah yang hilang
            // barisnya, berkasnya, atau penyimpanannya.
            abort(404);
        }

        $headers = [
            'Content-Type' => $attachment->mime ?: 'image/jpeg',

            // Cache di perangkat warga boleh, di cache bersama tidak: foto
            // warga memuat wajah dan bagian dalam rumah. Umurnya tidak
            // melebihi umur tanda tangannya.
            'Cache-Control' => 'private, max-age='.(int) config('nawasara-aspirations.storage.url_ttl', 900),

            // Ditampilkan, bukan diunduh, dan tidak ditafsirkan sebagai jenis
            // lain dari yang dinyatakan.
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ];

        // ⚠️ Tanpa Content-Length dari kolom `size`. Kalau angka itu berbeda
        // sedikit saja dari berkas yang sebenarnya tersimpan, peramban
        // memotong gambarnya tanpa galat apa pun. Dialirkan tanpa ukuran lebih
        // aman daripada ukuran yang mungkin keliru.
        return new StreamedResponse(function () use ($stream) {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $headers);
    }
}
