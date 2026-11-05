<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keterangan foto bukti, ditulis petugas.
 *
 * Panel OPD sudah mengirim `caption` sejak 26 Oktober 2026, dan selama kolom
 * ini belum ada Laravel membuangnya tanpa galat: petugas melihat "foto
 * tersimpan" sementara kalimatnya lenyap.
 *
 * Nullable: keterangan memang opsional, dan foto lama tidak punya.
 *
 * ⚠️ 500 karakter, bukan `text`. Ini kalimat penjelas ("Pengaspalan selesai
 * 24 Oktober, sepanjang 40 meter"), bukan laporan. Batas yang tegas membuat
 * panel dapat menghitung mundur sisa karakter, alih-alih membiarkan petugas
 * mengetik panjang lalu ditolak saat mengirim.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nawasara_aspirations_attachments', function (Blueprint $table) {
            $table->string('caption', 500)->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('nawasara_aspirations_attachments', function (Blueprint $table) {
            $table->dropColumn('caption');
        });
    }
};
