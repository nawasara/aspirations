<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Memindahkan kode kecamatan dari BPS ke Kemendagri.
 *
 * ## Kenapa ini tidak boleh dilewatkan
 *
 * Hanya 10 dari 21 kecamatan yang nomor urutnya kebetulan sama antara kedua
 * sistem. Tanpa pemetaan ini, laporan yang tersimpan sebagai BPS `3502010`
 * (Ngrayun) akan terbaca sebagai Kemendagri `350201` — yaitu **Slahung**.
 *
 * Laporannya tetap tampil, petanya tetap tergambar, dan tidak ada satu pun
 * galat. Yang berubah hanya: laporan warga Ngrayun mendarat di kecamatan
 * tetangga. Itulah sebabnya pemetaan ini dilakukan lewat NAMA, bukan lewat
 * pemotongan digit — memotong digit menghasilkan kode yang sah tetapi salah.
 *
 * ## Yang dipetakan
 *
 * `nawasara_aspirations_reports.district_code` dan
 * `nawasara_citizen_profiles.district_code`.
 *
 * Baris yang kodenya tidak dikenali DIBIARKAN apa adanya, bukan dikosongkan:
 * alamat yang hilang lebih sulit dipulihkan daripada alamat yang perlu
 * diperiksa ulang.
 */
return new class extends Migration
{
    /**
     * BPS → Kemendagri, dipasangkan lewat nama kecamatan.
     *
     * Ditulis eksplisit, bukan dihitung, supaya dapat dibaca dan diperiksa
     * orang tanpa menjalankan apa pun.
     */
    private const PETA = [
        '3502010' => ['350202', 'Ngrayun'],
        '3502020' => ['350201', 'Slahung'],
        '3502030' => ['350203', 'Bungkal'],
        '3502040' => ['350204', 'Sambit'],
        '3502050' => ['350205', 'Sawoo'],
        '3502060' => ['350206', 'Sooko'],
        '3502070' => ['350207', 'Pulung'],
        '3502080' => ['350208', 'Mlarak'],
        '3502090' => ['350210', 'Siman'],
        '3502100' => ['350209', 'Jetis'],
        '3502110' => ['350211', 'Balong'],
        '3502120' => ['350212', 'Kauman'],
        '3502130' => ['350220', 'Jambon'],
        '3502140' => ['350213', 'Badegan'],
        '3502150' => ['350214', 'Sampung'],
        '3502160' => ['350215', 'Sukorejo'],
        '3502170' => ['350217', 'Ponorogo'],
        '3502180' => ['350216', 'Babadan'],
        '3502190' => ['350218', 'Jenangan'],
        '3502200' => ['350219', 'Ngebel'],
        '3502210' => ['350221', 'Pudak'],
    ];

    public function up(): void
    {
        foreach (self::PETA as $bps => [$kemendagri, $nama]) {
            $this->petakan('nawasara_aspirations_reports', $bps, $kemendagri);
            $this->petakan('nawasara_citizen_profiles', $bps, $kemendagri);
        }

        // Baris kecamatan ber-kode BPS dibuang SETELAH datanya dipindahkan.
        //
        // Tanpa ini keduanya hidup berdampingan — 42 kecamatan untuk kabupaten
        // yang hanya punya 21 — dan daftar pilih di aplikasi menampilkan tiap
        // kecamatan dua kali. Dua sumber kebenaran untuk hal yang sama selalu
        // berakhir dengan satu yang basi dan tidak ada yang tahu mana.
        if (Schema::hasTable('nawasara_aspirations_districts')) {
            DB::table('nawasara_aspirations_districts')
                ->whereIn('code', array_keys(self::PETA))
                ->delete();
        }
    }

    public function down(): void
    {
        foreach (self::PETA as $bps => [$kemendagri, $nama]) {
            $this->petakan('nawasara_aspirations_reports', $kemendagri, $bps);
            $this->petakan('nawasara_citizen_profiles', $kemendagri, $bps);
        }
    }

    private function petakan(string $tabel, string $dari, string $ke): void
    {
        if (! Schema::hasTable($tabel) || ! Schema::hasColumn($tabel, 'district_code')) {
            return;
        }

        DB::table($tabel)->where('district_code', $dari)->update(['district_code' => $ke]);
    }
};
