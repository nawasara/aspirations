<?php

namespace Nawasara\Aspirations\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Nawasara\Aspirations\Exceptions\SubmissionException;
use Nawasara\Aspirations\Models\Attachment;
use Nawasara\Aspirations\Models\Report;
use Nawasara\Aspirations\Models\Response;
use Nawasara\Aspirations\Support\Settings;

/**
 * Unggah foto laporan & bukti tindak lanjut ke MinIO.
 *
 * Aturan foto ASIMETRIS dengan sengaja (D2):
 *
 *   foto WARGA   → wajib dari kamera (#21). Warga memang sedang di lokasi saat
 *                  melapor, dan itulah yang membuat titik GPS-nya bermakna.
 *   foto BUKTI   → kamera ATAU galeri. Petugas bisa memotret saat sinyal mati
 *                  lalu mengunggah dari kantor, atau memakai kamera dinas.
 *                  Memaksa kamera aplikasi akan menghukum petugas yang bekerja
 *                  jujur di daerah bersinyal buruk.
 *
 * Untuk foto bukti, EXIF DICATAT tetapi tidak pernah memblokir — penandanya
 * untuk mata Kabid saat memverifikasi.
 */
class PhotoUploader
{
    /**
     * Simpan satu foto laporan warga.
     *
     * @param  string  $source  'camera' | 'gallery' — dilaporkan aplikasi.
     */
    public function storeReportPhoto(Report $report, UploadedFile $file, string $source = 'camera'): Attachment
    {
        // Jenis & ukuran berkas diperiksa LEBIH DULU. Bila batas foto yang
        // diperiksa duluan, berkas bukan-gambar yang diunggah ke laporan yang
        // masih kosong akan lolos pemeriksaan jenis sama sekali — dan pesan
        // galatnya pun salah ("maksimal 3 foto" untuk sebuah PDF).
        $this->assertAcceptable($file);
        $this->assertWithinPhotoLimit($report);

        // #21 — foto warga wajib dari kamera. Ditegakkan di server meski
        // aplikasi sudah tidak menawarkan galeri: aplikasi dapat dimodifikasi,
        // dan permintaan dapat dikirim langsung ke API.
        if ($source !== Attachment::SOURCE_CAMERA) {
            throw new SubmissionException(
                'Foto laporan harus diambil langsung dari kamera agar lokasi dan waktu tercatat.'
            );
        }

        return $this->store($report, $file, Attachment::KIND_REPORT, $source);
    }

    /**
     * Simpan foto bukti tindak lanjut.
     *
     * Sumber bebas — lihat catatan kelas. Yang dicatat adalah penandanya.
     */
    public function storeEvidencePhoto(
        Report $report,
        Response $response,
        UploadedFile $file,
        string $source = 'unknown',
    ): Attachment {
        $this->assertAcceptable($file);

        return $this->store($report, $file, Attachment::KIND_EVIDENCE, $source, $response);
    }

    protected function store(
        Report $report,
        UploadedFile $file,
        string $kind,
        string $source,
        ?Response $response = null,
    ): Attachment {
        $disk = (string) config('nawasara-aspirations.storage.disk', 'minio');

        // Kunci objek disusun agar mudah ditelusuri manusia saat memeriksa
        // bucket, dan ULID di ujungnya mencegah dua unggahan bertabrakan.
        $path = sprintf(
            'aspirations/%s/%s/%s/%s.%s',
            $report->received_at->format('Y'),
            $report->received_at->format('m'),
            $report->code,
            (string) Str::ulid(),
            $file->getClientOriginalExtension() ?: 'jpg',
        );

        Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()));

        $exif = $this->readExif($file);

        return Attachment::create([
            'report_id' => $report->id,
            'response_id' => $response?->id,

            // Dicatat per baris, bukan diasumsikan dari config — kalau
            // penyimpanan pindah, berkas lama tetap ketemu.
            'disk' => $disk,
            'path' => $path,

            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'checksum_sha256' => hash_file('sha256', $file->getRealPath()),

            'kind' => $kind,
            'source' => $source,

            // ⚠️ NULL berarti TIDAK DIKETAHUI, bukan mencurigakan. WhatsApp
            // membuang EXIF, dan banyak ponsel mematikan penandaan lokasi.
            // Memperlakukan NULL sebagai sinyal akan menandai hampir semua foto
            // bukti, sehingga penandanya kehilangan arti.
            'captured_at' => $exif['captured_at'],
            'captured_lat' => $exif['lat'],
            'captured_lng' => $exif['lng'],
        ]);
    }

    /** Foto per laporan dibatasi (#), divalidasi di SERVER bukan hanya aplikasi. */
    protected function assertWithinPhotoLimit(Report $report): void
    {
        $max = Settings::photosPerReport();

        $sudah = $report->attachments()->where('kind', Attachment::KIND_REPORT)->count();

        if ($sudah >= $max) {
            throw new SubmissionException("Maksimal {$max} foto per laporan.");
        }
    }

    protected function assertAcceptable(UploadedFile $file): void
    {
        $maxKb = Settings::photoMaxKb();

        if ($file->getSize() > $maxKb * 1024) {
            throw new SubmissionException(
                'Ukuran foto melebihi '.round($maxKb / 1024, 1).' MB.'
            );
        }

        // Diperiksa dari ISI berkas, bukan dari ekstensi atau header yang
        // dikirim pemanggil — keduanya dapat dipalsukan.
        if (! in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new SubmissionException('Berkas harus berupa gambar JPG, PNG, atau WebP.');
        }
    }

    /**
     * Baca waktu & koordinat dari EXIF.
     *
     * Dibungkus penuh: berkas tanpa EXIF, EXIF rusak, atau ekstensi `exif` yang
     * tidak terpasang tidak boleh menggagalkan unggahan. Foto tetap berharga
     * meski penandanya tidak ada.
     */
    protected function readExif(UploadedFile $file): array
    {
        $kosong = ['captured_at' => null, 'lat' => null, 'lng' => null];

        if (! function_exists('exif_read_data') || $file->getMimeType() !== 'image/jpeg') {
            return $kosong;
        }

        try {
            $data = @exif_read_data($file->getRealPath());
        } catch (\Throwable) {
            return $kosong;
        }

        if (! is_array($data)) {
            return $kosong;
        }

        $waktu = $data['DateTimeOriginal'] ?? $data['DateTime'] ?? null;

        return [
            'captured_at' => $waktu ? $this->parseExifTime($waktu) : null,
            'lat' => $this->gpsToDecimal($data, 'GPSLatitude', 'GPSLatitudeRef'),
            'lng' => $this->gpsToDecimal($data, 'GPSLongitude', 'GPSLongitudeRef'),
        ];
    }

    /** EXIF memakai "2026:08:15 10:30:00" — bukan format yang dikenal Carbon. */
    protected function parseExifTime(string $value): ?\Illuminate\Support\Carbon
    {
        try {
            return \Illuminate\Support\Carbon::createFromFormat('Y:m:d H:i:s', $value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** GPS EXIF disimpan sebagai derajat/menit/detik dalam bentuk pecahan. */
    protected function gpsToDecimal(array $exif, string $key, string $refKey): ?float
    {
        if (empty($exif[$key]) || ! is_array($exif[$key])) {
            return null;
        }

        $bagi = function ($pecahan) {
            if (! str_contains((string) $pecahan, '/')) {
                return (float) $pecahan;
            }

            [$atas, $bawah] = explode('/', (string) $pecahan);

            return (float) $bawah === 0.0 ? 0.0 : (float) $atas / (float) $bawah;
        };

        $derajat = $bagi($exif[$key][0] ?? 0);
        $menit = $bagi($exif[$key][1] ?? 0);
        $detik = $bagi($exif[$key][2] ?? 0);

        $nilai = $derajat + ($menit / 60) + ($detik / 3600);

        // S dan W bernilai negatif — Ponorogo ada di lintang selatan, jadi
        // melewatkan ini akan menempatkan setiap laporan di belahan bumi utara.
        $ref = strtoupper((string) ($exif[$refKey] ?? ''));

        return in_array($ref, ['S', 'W'], true) ? -$nilai : $nilai;
    }
}
