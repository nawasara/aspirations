<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda "masa penilaian sudah lewat" (#7).
     *
     * Dipisah dari `rating` dengan sengaja. Bila laporan tanpa penilaian diberi
     * nilai bawaan agar terlihat tuntas, rerata kepuasan menjadi campuran
     * antara pendapat warga dan angka yang tidak pernah dikatakan siapa pun —
     * dan rerata itulah yang dibaca pimpinan di Dashboard Bunda.
     *
     * Dengan kolom terpisah: `rating` NULL berarti warga memang tidak menilai,
     * dan `rated_closed_at` terisi berarti sistem berhenti menunggunya.
     */
    public function up(): void
    {
        Schema::table('nawasara_aspirations_reports', function (Blueprint $table) {
            $table->timestamp('rated_closed_at')->nullable()->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('nawasara_aspirations_reports', function (Blueprint $table) {
            $table->dropColumn('rated_closed_at');
        });
    }
};
