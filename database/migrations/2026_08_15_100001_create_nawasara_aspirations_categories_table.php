<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kategori laporan — 13 urusan hasil rapat 13 Agustus 2026.
     *
     * Di-seed dari config, lalu DIKELOLA LEWAT PANEL. Nama, warna, SLA, OPD
     * pengampu, dan urutan boleh berubah tanpa merilis ulang aplikasi warga:
     * kalau SLA "Sampah" berubah dari 3 hari jadi 2 hari, itu satu baris di
     * panel — bukan menunggu peninjauan Play Store lalu berharap warga
     * memperbarui aplikasinya.
     */
    public function up(): void
    {
        Schema::create('nawasara_aspirations_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // ⚠️ TIDAK BOLEH BERUBAH setelah kategori dipakai melapor.
            // `code` adalah kunci pemetaan ikon di Flutter DAN rujukan laporan
            // lama. Label boleh berganti ("Sampah" → "Sampah & Kebersihan");
            // code tidak. Panel wajib menolak penyuntingannya bila kategori
            // sudah punya laporan — bukan sekadar menyembunyikan kolomnya.
            $table->string('code')->unique();

            $table->string('name');

            // Contoh keluhan, tampil di bawah label supaya warga memilih benar.
            // Diisi dari 42 sub-item hasil rapat. Sengaja TIDAK menjadi tingkat
            // kedua: 42 pilihan mustahil disodorkan di alur 2 menit, dan
            // cabangnya sangat timpang (Ketertiban 7, Tenaga Kerja 1) — pohon
            // setimpang begitu paling menyulitkan warga lansia.
            $table->text('hint')->nullable();

            // Nama SIMBOLIK, bukan berkas/URL. Flutter memetakannya ke ikon
            // yang sudah dikompilasi ke dalam APK; nama tak dikenal jatuh ke
            // ikon "lainnya". Tanpa fallback itu, satu kategori baru merusak
            // layar pertama bagi semua warga yang belum memperbarui aplikasi.
            $table->string('icon_name');

            // Nama warna Tailwind ('stone', 'amber'), bukan kode heksadesimal —
            // agar tetap sejalan bila palet berubah.
            $table->string('color')->default('slate');

            // Tujuan disposisi otomatis (#18). WAJIB terisi untuk setiap
            // kategori aktif; tanpa itu laporan berhenti di pintu masuk.
            // Nullable di tingkat basis data karena seed berjalan sebelum
            // registry OPD tentu terisi — validasinya di service, bukan di sini.
            $table->foreignId('opd_id')->nullable()
                ->constrained('nawasara_registry_opd')->nullOnDelete();

            // 3 jam (bencana) s.d. 720 jam (30 hari, aspirasi).
            $table->unsignedInteger('sla_hours')->default(336);

            // Hari KALENDER adalah default — warga tidak membedakan hari libur
            // ketika saluran air mampet, dan SLA 3 jam mustahil memakai hari
            // kerja. Nyalakan per kategori HANYA bila sudah ada yang memelihara
            // daftar libur nasional tiap tahun; bila terlupa, tenggat meleset
            // diam-diam.
            $table->boolean('uses_working_days')->default(false);

            $table->unsignedSmallInteger('sort_order')->default(0);

            // Nonaktifkan, JANGAN hapus. Kategori yang pernah dipakai melapor
            // harus tetap ada agar laporan lama tetap terbaca.
            $table->boolean('is_active')->default(true);

            // Urusan Pemerintahan (pungli, kelalaian aparatur). Laporan di sini
            // tidak boleh didisposisi ke OPD yang dilaporkan (#9), sekalipun
            // secara administratif tampak lebih tepat.
            $table->boolean('is_sensitive')->default(false);

            // DISIAPKAN, TIDAK DIPAKAI — semua NULL. Selama NULL, perilakunya
            // persis datar. Menambah kolom ini belakangan, saat sudah ada
            // ratusan ribu laporan, jauh lebih mahal daripada sekarang.
            $table->foreignUuid('parent_id')->nullable()
                ->constrained('nawasara_aspirations_categories')->nullOnDelete();

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_aspirations_categories');
    }
};
