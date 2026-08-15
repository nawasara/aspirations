<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laporan warga — tabel inti Lapor Bunda.
     */
    public function up(): void
    {
        Schema::create('nawasara_aspirations_reports', function (Blueprint $table) {
            $table->id();

            // Kode publik (LB-2026-08-0412), bukan id basis data. Urut PER
            // BULAN, bukan global — lihat AspirationCode.
            $table->string('code')->unique();

            // ⚠️ SELALU terisi, termasuk saat is_anonymous = true.
            // "Anonim" berarti NAMA disembunyikan dari tampilan, BUKAN pelapor
            // tak dikenal — setiap pelapor wajib masuk lebih dulu. Beberapa
            // aturan bergantung pada ini: batas 5 laporan/hari (#12) tetap
            // berlaku, dan laporan palsu tetap dapat ditelusuri bila ada
            // proses hukum.
            $table->string('keycloak_sub')->index();

            $table->foreignId('category_id')
                ->constrained('nawasara_aspirations_categories');

            // Hasil disposisi otomatis saat laporan masuk (#18).
            $table->foreignId('opd_id')->nullable()
                ->constrained('nawasara_registry_opd')->nullOnDelete();

            // ENAM status. `reviewing` dihapus (tidak ada lagi verifikasi Admin
            // Kabupaten di depan) dan `scheduled` dihapus (pekerjaan menunggu
            // anggaran dijawab lewat tanggapan, lalu laporan ditutup — status
            // menggantung membuat laporan tak pernah tuntas dan tak masuk
            // hitungan siapa pun). Label Indonesia ada di berkas pesan.
            $table->string('status')->default('submitted')->index();

            // Hanya menyembunyikan nama di tampilan. Disaring di API Resource,
            // BUKAN di antarmuka — menyembunyikan di panel berarti datanya
            // tetap terkirim ke browser (#8).
            $table->boolean('is_anonymous')->default(false);

            $table->string('title');
            $table->text('description');

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Meter, dari GPS ponsel. Laporan berakurasi 500 m tidak layak
            // dipakai deteksi ganda radius 50 m.
            $table->decimal('location_accuracy', 8, 2)->nullable();

            $table->text('full_address')->nullable();
            $table->string('village')->nullable();
            $table->string('district')->nullable();

            // Hasil pencocokan ke master wilayah — BOLEH kosong bila geocoding
            // gagal; laporan tetap sah.
            $table->unsignedBigInteger('village_id')->nullable()->index();

            // ⚠️ Waktu DIBUAT di ponsel vs DITERIMA server (#13). Warga di
            // wilayah tanpa sinyal membuat laporan Senin, terkirim Rabu.
            // Keduanya disimpan; kebijakan mana yang dipakai menghitung SLA
            // ditetapkan kemudian. Menambahkan kolom ini belakangan berarti
            // data lama tidak punya nilainya.
            $table->timestamp('created_at_device')->nullable();
            $table->timestamp('received_at');

            // ── SLA: target (batas) vs aktual (tercapai) ──
            // Target dipakai mendeteksi keterlambatan saat berjalan; aktual
            // dipakai menghitung kinerja. Semua dihitung dari received_at,
            // BUKAN berantai — kalau berantai, OPD yang lambat menanggapi
            // justru mendapat tenggat selesai yang mundur.
            $table->timestamp('response_due_at')->nullable();
            $table->timestamp('first_responded_at')->nullable();
            $table->timestamp('sla_due_at')->nullable()->index();
            $table->timestamp('resolution_submitted_at')->nullable();

            // SLA yang DITAMPILKAN ke warga saat mengirim — itulah janjinya.
            // Disimpan terpisah dari category.sla_hours yang bisa berubah
            // kemudian.
            $table->unsignedInteger('promised_sla_hours')->nullable();

            $table->unsignedTinyInteger('escalation_level')->default(0);

            // Staf OPD yang mengerjakan.
            $table->foreignId('responded_by')->nullable()
                ->constrained('users')->nullOnDelete();

            // Kabid yang DITUNJUK petugas saat menyerahkan (D1b). Antrean
            // verifikasi difilter dengan kolom ini.
            $table->foreignId('verifier_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // Kabid yang BENAR-BENAR menyetujui. Biasanya sama dengan
            // verifier_id; berbeda bila yang ditunjuk berhalangan — dan justru
            // saat berbeda itulah yang perlu terlihat saat audit.
            // ⚠️ WAJIB != responded_by (#16), ditegakkan di server.
            $table->foreignId('verified_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamp('verified_at')->nullable();

            // Batas Kabid memverifikasi. Tanpa ini laporan menggantung selamanya
            // di awaiting_verification — warga melihat "sedang diproses"
            // padahal pekerjaannya sudah selesai (#19).
            $table->timestamp('verification_due_at')->nullable();

            $table->unsignedTinyInteger('rating')->nullable();

            // Denormalisasi: dipakai mengurutkan prioritas (#15). COUNT() per
            // baris terlalu mahal di daftar yang terus tumbuh.
            $table->unsignedInteger('support_count')->default(0);

            $table->timestamps();

            // Antrean Admin OPD: laporan miliknya, diurut tenggat terdekat.
            $table->index(['opd_id', 'status']);
            // Antrean verifikasi Kabid.
            $table->index(['verifier_id', 'status']);
            // Deteksi ganda + peta.
            $table->index(['category_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_aspirations_reports');
    }
};
