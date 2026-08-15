<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda hasil saringan isi (#20).
     *
     * Menggantikan pemeriksaan Admin Kabupaten yang dihapus D1. Laporan yang
     * ditandai TIDAK ditolak — ia tetap tersimpan dan tetap punya tenggat,
     * hanya belum diteruskan ke OPD sampai ditinjau manusia.
     *
     * Warga yang marah sering memakai kata kasar, dan kemarahannya sah. Menolak
     * laporannya berarti menghukum orang karena nada bicaranya lalu kehilangan
     * isinya — padahal isinya yang berguna.
     */
    public function up(): void
    {
        Schema::table('nawasara_aspirations_reports', function (Blueprint $table) {
            // Terisi = menunggu tinjauan. Diindeks karena panel Admin Kabupaten
            // menyaring tepat pada kolom ini.
            $table->timestamp('flagged_at')->nullable()->after('status')->index();

            // Alasan penandaan: kata_kasar, sara, huruf_kapital_berlebih.
            // Disimpan agar peninjau tahu apa yang dicurigai tanpa harus
            // menebak dari teksnya.
            $table->json('flag_reasons')->nullable()->after('flagged_at');

            // Siapa yang meloloskan, dan kapan. Tanpa jejak ini, keputusan
            // meloloskan laporan bermuatan SARA tidak dapat ditelusuri.
            $table->foreignId('cleared_by')->nullable()->after('flag_reasons')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable()->after('cleared_by');
        });
    }

    public function down(): void
    {
        Schema::table('nawasara_aspirations_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cleared_by');
            $table->dropColumn(['flagged_at', 'flag_reasons', 'cleared_at']);
        });
    }
};
