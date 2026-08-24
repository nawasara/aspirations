<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kecamatan hasil pencocokan koordinat, disimpan pada laporannya.
 *
 * Dihitung sekali saat laporan masuk, bukan tiap permintaan peta: mencocokkan
 * titik ke 21 kecamatan untuk ribuan laporan pada setiap pemuatan peta adalah
 * pekerjaan yang sama, diulang terus, untuk jawaban yang tidak berubah.
 *
 * Terpisah dari kolom `district` yang sudah ada — kolom itu milik
 * reverse-geocoding Google dan berisi NAMA sebagaimana Google menuliskannya.
 * Yang ini kode BPS dari master kecamatan, dan keduanya boleh berbeda:
 * geocoding bisa menyebut "Kec. Ngebel" sementara master menyebut "Ngebel".
 * Menimpa kolom lama akan menghapus jejak apa yang sebenarnya dikatakan
 * Google.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nawasara_aspirations_reports', function (Blueprint $table) {
            $table->string('district_code', 10)->nullable()->after('district');

            // Peta menyaring `district_code` + `status` bersamaan.
            $table->index(['district_code', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('nawasara_aspirations_reports', function (Blueprint $table) {
            $table->dropIndex(['district_code', 'status']);
            $table->dropColumn('district_code');
        });
    }
};
