<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Saya Juga Mengalami" — dukungan warga atas laporan yang sudah ada.
     *
     * Mengurangi laporan ganda sekaligus menunjukkan mana yang dirasakan
     * banyak orang; jumlahnya menaikkan prioritas (#15).
     */
    public function up(): void
    {
        Schema::create('nawasara_aspirations_supports', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('report_id')
                ->constrained('nawasara_aspirations_reports')->cascadeOnDelete();

            $table->string('keycloak_sub');

            $table->timestamps();

            // Satu warga satu dukungan per laporan — ditegakkan basis data,
            // bukan hanya dicek di service. Pemeriksaan di service bisa lolos
            // saat dua permintaan datang bersamaan.
            $table->unique(['report_id', 'keycloak_sub']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_aspirations_supports');
    }
};
