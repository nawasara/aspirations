<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foto laporan warga & foto bukti tindak lanjut OPD.
     *
     * Berkasnya di MinIO, bukan di disk aplikasi: aplikasi berjalan di
     * beberapa container, sehingga berkas yang ditulis satu container tidak
     * terlihat oleh yang lain dan hilang saat container dibuat ulang.
     */
    public function up(): void
    {
        Schema::create('nawasara_aspirations_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('report_id')
                ->constrained('nawasara_aspirations_reports')->cascadeOnDelete();

            // Terisi bila foto ini bukti tindak lanjut, bukan foto warga.
            $table->foreignUuid('response_id')->nullable()
                ->constrained('nawasara_aspirations_responses')->cascadeOnDelete();

            // Dicatat PER BARIS, bukan diasumsikan dari config — kalau bucket
            // atau penyimpanan pindah, berkas lama tetap ketemu.
            $table->string('disk')->default('minio');

            // Kunci objek di bucket, BUKAN URL. URL presigned dibuat saat
            // diminta dan kedaluwarsa; menyimpannya berarti tautan mati
            // diam-diam beberapa menit kemudian.
            $table->string('path');

            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('checksum_sha256', 64)->nullable();

            // 'report' = foto warga, 'evidence' = foto bukti OPD.
            $table->string('kind')->default('report');

            // ── Penanda keaslian (D2) ──
            // Foto WARGA wajib dari kamera; foto BUKTI OPD boleh dari galeri.
            // Asimetris dengan sengaja: warga memang sedang di lokasi, petugas
            // bisa saja mengunggah dari kantor karena sinyal buruk di lapangan.
            //
            // ⚠️ Untuk bukti, kolom ini MENANDAI bagi Kabid — tidak memblokir.
            // Dan NULL berarti "tidak diketahui", BUKAN mencurigakan: WhatsApp
            // membuang EXIF, jadi foto bukti yang sampai lewat WA akan sering
            // kosong. Kalau NULL dianggap sinyal, hampir semua foto tertandai
            // dan petugas belajar mengabaikan penandanya.
            $table->timestamp('captured_at')->nullable();
            $table->decimal('captured_lat', 10, 7)->nullable();
            $table->decimal('captured_lng', 10, 7)->nullable();
            $table->string('source')->default('unknown');

            // Kapan boleh dihapus — diisi saat unggah dari kebijakan yang
            // berlaku waktu itu. Disimpan per baris supaya perubahan kebijakan
            // TIDAK berlaku surut: memperketat aturan arsip tidak boleh
            // memusnahkan bukti laporan yang masih berjalan.
            //
            // NULL = belum ada kebijakan → tidak dihapus. Itu default yang
            // aman; kehilangan bukti tidak bisa dibatalkan.
            $table->date('purge_after')->nullable()->index();

            $table->timestamps();

            $table->index(['report_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_aspirations_attachments');
    }
};
