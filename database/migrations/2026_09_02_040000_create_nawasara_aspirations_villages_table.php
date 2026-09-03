<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 307 desa/kelurahan Kabupaten Ponorogo.
 *
 * Ada supaya alamat warga dapat DIPILIH, bukan diketik. Alamat menentukan ke
 * OPD mana laporan disalurkan, dan pengetikan bebas membuat satu tempat
 * tercatat dalam beberapa ejaan — laporan tersalur ke dinas yang keliru tanpa
 * pelapornya pernah tahu.
 *
 * Kode Kemendagri 10 digit, BERAWALAN kode kecamatannya (350202 → 3502022001).
 * Sifat itu yang membuat pasangan desa–kecamatan dapat diperiksa tanpa join.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_aspirations_villages', function (Blueprint $table) {
            $table->id();

            // 10 digit, mis. 3502022001. String, bukan angka: sebagian kode
            // wilayah di Indonesia berawalan nol, dan sebagai angka nol itu
            // hilang lalu kodenya tidak cocok dengan apa pun.
            $table->string('code', 10)->unique();

            // 6 digit. Sengaja TIDAK memakai foreign key ke tabel kecamatan:
            // kecocokannya sudah dijamin awalan kode, dan FK di sini hanya
            // mempersulit seeding ulang saat ada pemekaran wilayah.
            $table->string('district_code', 6)->index();

            $table->string('name', 100);

            // Kelurahan (kode 1xxx) berbeda dari desa (2xxx): kelurahan
            // dipimpin lurah dan tidak punya pemerintahan desa sendiri. Empat
            // kecamatan di Ponorogo memuat keduanya, jadi perbedaannya tidak
            // dapat disimpulkan dari kecamatannya.
            $table->boolean('is_kelurahan')->default(false);

            $table->timestamps();

            $table->index(['district_code', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_aspirations_villages');
    }
};
