<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Apakah kategori ini memerlukan foto bukti untuk ditutup (#3)?
     *
     * Aturan #3 semula berlaku untuk semua laporan, dan itu keliru: tidak semua
     * laporan berujung pada pekerjaan fisik yang dapat difoto.
     *
     *   Infrastruktur  → jalan ditambal, ada yang bisa dipotret
     *   Aspirasi       → usulan warga dijawab, tidak ada apa pun untuk dipotret
     *   Pemerintahan   → pungli diperiksa Inspektorat, hasilnya berkas bukan foto
     *
     * Memaksa foto di kategori seperti itu mendorong petugas MEMOTRET APA SAJA
     * sekadar agar tombol "selesai" dapat ditekan — dan foto bukti asal-asalan
     * lebih buruk daripada tidak ada foto, karena membuat seluruh bukti
     * kehilangan arti.
     *
     * Default `true`: kategori baru harus menyertakan bukti kecuali sengaja
     * dinyatakan tidak perlu. Bawaan yang longgar akan membuat kategori yang
     * lupa disetel diam-diam kehilangan penjaganya.
     */
    public function up(): void
    {
        Schema::table('nawasara_aspirations_categories', function (Blueprint $table) {
            $table->boolean('requires_evidence')->default(true)->after('is_sensitive');
        });
    }

    public function down(): void
    {
        Schema::table('nawasara_aspirations_categories', function (Blueprint $table) {
            $table->dropColumn('requires_evidence');
        });
    }
};
