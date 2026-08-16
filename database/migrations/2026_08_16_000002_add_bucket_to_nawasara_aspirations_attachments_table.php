<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catat bucket tempat setiap berkas benar-benar disimpan.
 *
 * Alasannya sama dengan kolom `disk` yang sudah ada: konfigurasi berubah, dan
 * berkas yang sudah tersimpan tidak ikut pindah. Tanpa kolom ini, mengubah
 * bucket di config akan membuat SELURUH foto lama tidak terbaca — presigned
 * URL-nya menunjuk bucket baru yang tidak memuatnya, dan galatnya berupa 404
 * yang tampak seperti berkasnya hilang.
 *
 * Nullable, dan null berarti "bucket bawaan dari Vault" — baris lama yang
 * dibuat sebelum pemisahan bucket tetap terbaca tanpa perlu ditulis ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nawasara_aspirations_attachments', function (Blueprint $table) {
            $table->string('bucket', 128)->nullable()->after('disk');
        });
    }

    public function down(): void
    {
        Schema::table('nawasara_aspirations_attachments', function (Blueprint $table) {
            $table->dropColumn('bucket');
        });
    }
};
