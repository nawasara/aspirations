<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tanggapan OPD + riwayat perubahan status.
     *
     * Satu baris per perubahan, tidak pernah disunting — inilah linimasa yang
     * dilihat warga di detail laporan, sekaligus jejak audit siapa melakukan
     * apa dan kapan.
     */
    public function up(): void
    {
        Schema::create('nawasara_aspirations_responses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('report_id')
                ->constrained('nawasara_aspirations_reports')->cascadeOnDelete();

            // Pelaku. Ditampilkan di linimasa ("oleh: Admin DPUPKP") supaya
            // warga tahu ada manusia yang menangani, bukan sistem anonim.
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('status_from')->nullable();
            $table->string('status_to')->nullable();

            // Tanggapan wajib diisi saat mengubah status (#4). Yang terlihat
            // warga inilah yang menentukan first_responded_at — bukan
            // pergeseran status tanpa sepatah kata pun.
            $table->text('body')->nullable();

            // Tanggapan internal tidak tampil ke warga — mis. koordinasi
            // antar-bidang sebelum jawaban resmi disusun.
            $table->boolean('is_internal')->default(false);

            // (tidak ada `scheduled_for` — status `scheduled` dihapus;
            //  pekerjaan yang menunggu anggaran dinyatakan di `body`)

            $table->timestamps();

            $table->index(['report_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_aspirations_responses');
    }
};
