<?php

namespace Nawasara\Aspirations\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Foto laporan warga & foto bukti tindak lanjut.
 *
 * `path` adalah kunci objek, bukan URL. URL dibuat saat diminta dan
 * kedaluwarsa — menyimpannya berarti tautan mati diam-diam beberapa menit
 * kemudian.
 */
class Attachment extends Model
{
    use HasUuids;

    protected $table = 'nawasara_aspirations_attachments';

    protected $guarded = [];

    protected $casts = [
        'captured_at' => 'datetime',
        'purge_after' => 'date',
        'size' => 'integer',
    ];

    public const KIND_REPORT = 'report';
    public const KIND_EVIDENCE = 'evidence';

    public const SOURCE_CAMERA = 'camera';
    public const SOURCE_GALLERY = 'gallery';
    public const SOURCE_UNKNOWN = 'unknown';

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id');
    }

    /**
     * URL sementara untuk menampilkan foto.
     *
     * Bucket privat, jadi harus presigned. Dibuat per permintaan; jangan
     * disimpan ke basis data atau di-cache melewati umurnya.
     */
    public function temporaryUrl(): ?string
    {
        $ttl = (int) config('nawasara-aspirations.storage.url_ttl', 900);

        try {
            // Bucket yang TERCATAT pada baris ini, bukan yang sedang disetel di
            // config. Bila bucket pernah diganti, foto lama tetap harus terbaca
            // dari tempat ia benar-benar disimpan.
            $disk = ($this->disk === 'minio' && $this->bucket
                && class_exists(\Nawasara\Vault\Services\MinioDisk::class))
                    ? \Nawasara\Vault\Services\MinioDisk::make($this->bucket)
                    : Storage::disk($this->disk);

            return $disk->temporaryUrl($this->path, now()->addSeconds($ttl));
        } catch (\Throwable) {
            // Disk belum terpasang atau berkas hilang: kembalikan null supaya
            // satu foto rusak tidak menjatuhkan seluruh halaman laporan.
            return null;
        }
    }

    /**
     * Apakah foto ini perlu diperiksa lebih teliti oleh Kabid?
     *
     * ⚠️ MENANDAI, bukan memblokir — dan hanya untuk foto BUKTI. NULL berarti
     * "tidak diketahui", BUKAN mencurigakan: WhatsApp membuang EXIF, jadi foto
     * yang sampai lewat WA akan sering kosong. Kalau NULL dianggap sinyal,
     * hampir semua foto tertandai dan penandanya kehilangan arti.
     */
    public function isSuspect(): bool
    {
        if ($this->kind !== self::KIND_EVIDENCE) {
            return false;
        }

        // Diambil sebelum laporan dibuat — foto lama yang dipakai ulang.
        if ($this->captured_at && $this->report
            && $this->captured_at->lt($this->report->received_at)) {
            return true;
        }

        return false;
    }
}
