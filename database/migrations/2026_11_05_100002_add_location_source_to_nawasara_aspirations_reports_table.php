<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Asal titik lokasi: `gps` atau `manual` (ditunjuk warga di peta).
 *
 * NULL berarti laporan tanpa titik sama sekali. Lihat Support\LocationSource.
 *
 * ⚠️ Laporan lama yang BERTITIK diisi `gps`. Sebelum kolom ini ada, aplikasi
 * tidak menyediakan cara lain untuk mengirim titik (kontrak API: warga tidak
 * dapat menggeser titik), jadi setiap koordinat yang tersimpan memang berasal
 * dari GPS. Membiarkannya NULL akan membuat laporan lama tampak "tanpa titik"
 * padahal koordinatnya ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nawasara_aspirations_reports', function (Blueprint $table) {
            $table->string('location_source', 10)->nullable()->after('location_accuracy');
        });

        DB::table('nawasara_aspirations_reports')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->update(['location_source' => 'gps']);
    }

    public function down(): void
    {
        Schema::table('nawasara_aspirations_reports', function (Blueprint $table) {
            $table->dropColumn('location_source');
        });
    }
};
