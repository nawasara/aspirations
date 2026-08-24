<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master kecamatan Ponorogo — 21 baris, untuk mengelompokkan laporan di peta.
 *
 * ⚠️ **Ada karena geocoding TIDAK dapat diandalkan di sini.**
 *
 * Kolom `district` pada laporan diisi oleh reverse-geocoding Google, dan
 * bawaan sistem adalah `NullGeocoder` — sengaja, supaya laporan warga tetap
 * masuk tanpa kunci pihak ketiga. Akibatnya di produksi (24 Agustus 2026)
 * **sepuluh dari sepuluh laporan punya koordinat, dan NOL punya `district`**.
 * Peta yang mengelompokkan lewat kolom itu akan selalu kosong.
 *
 * Koordinatnya yang selalu ada. Maka pengelompokan dilakukan dari titik ke
 * kecamatan terdekat lewat tabel ini — tidak menuntut jaringan, tidak
 * menuntut kunci, dan bekerja untuk laporan lama maupun baru.
 *
 * Kodenya kode BPS, supaya kelak dapat disandingkan dengan data pemerintah
 * lain tanpa penerjemahan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_aspirations_districts', function (Blueprint $table) {
            $table->id();

            // Kode BPS, mis. 3502140. Dipakai aplikasi sebagai `district_code`.
            $table->string('code', 10)->unique();

            $table->string('name', 100);

            // Titik tengah kecamatan — dipakai menaruh penanda di peta, dan
            // menentukan kecamatan terdekat dari koordinat laporan.
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_aspirations_districts');
    }
};
