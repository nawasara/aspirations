<?php

declare(strict_types=1);

namespace Nawasara\Aspirations\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Nawasara\Aspirations\Models\Attachment;
use Nawasara\Aspirations\Models\Category;
use Nawasara\Aspirations\Models\District;
use Nawasara\Aspirations\Models\Report;
use Nawasara\Aspirations\Models\Response;

/**
 * Laporan warga contoh — mengisi panel staf saat pengembangan.
 *
 * Sebaran statusnya SENGAJA tidak rata. Halaman daftar yang diisi satu laporan
 * per status terlihat baik-baik saja dan justru menyembunyikan yang perlu
 * diuji: antrean sungguhan menumpuk di `in_progress`, dan yang lewat batas
 * waktu selalu sedikit. Sebaran di bawah meniru bentuk itu, sehingga saringan
 * "lewat batas waktu" mengembalikan segenggam baris — bukan nol (tampak rusak)
 * atau separuh tabel (tampak salah hitung).
 *
 * Yang ikut dibentuk, karena ketiganya pernah tersimpan berbulan-bulan tanpa
 * satu pun halaman menampilkannya:
 *
 * - **foto** laporan dan foto bukti, termasuk satu yang waktu ambilnya lebih
 *   awal dari laporannya supaya penanda "Perlu dicek" ikut terlihat;
 * - **riwayat** tanggapan, termasuk catatan internal yang tak dibaca warga;
 * - **penilaian** bintang, dukungan, dan laporan yang masa penilaiannya
 *   ditutup tanpa dinilai — tiga keadaan yang tampil berbeda.
 *
 * ⚠️ Laporan dibuat LANGSUNG lewat model, bukan `ReportSubmission::submit()`.
 * Submit menerapkan batas harian, penyaringan isi, dan disposisi otomatis;
 * melewatinya di sini diterima karena tujuannya mengisi tampilan, bukan menguji
 * alur masuk. Untuk menguji alur masuk, pakai `ReportSubmission`.
 *
 * ⚠️ HANYA untuk pengembangan. Kodenya diberi awalan khusus (lihat PREFIX)
 * supaya dapat dicabut tepat sasaran; tanpa penanda begitu, data contoh dan
 * data sungguhan tidak dapat dipisahkan lagi setelah tercampur.
 */
class SampleReportSeeder extends Seeder
{
    /**
     * Awalan kode laporan contoh.
     *
     * Kode sungguhan berbentuk `LB-2026-09-0001`; yang contoh memakai `LBX`
     * supaya sekali pandang terlihat bedanya, dan supaya penghapusannya dapat
     * menyasar tepat baris ini saja.
     */
    public const PREFIX = 'LBX';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('SampleReportSeeder dilewati: lingkungan produksi.');

            return;
        }

        $this->seed();
    }

    public function seed(): void
    {
        $kategori = Category::orderBy('sort_order')->get();
        $kecamatan = District::orderBy('name')->get();

        if ($kategori->isEmpty() || $kecamatan->isEmpty()) {
            $this->command?->warn(
                '  Kategori atau kecamatan kosong — jalankan CategorySeeder & DistrictSeeder dulu.'
            );

            return;
        }

        // Idempoten: menjalankan ulang mengganti isi contoh, bukan menggandakan.
        $this->purge();

        $petugas = DB::table('users')->orderBy('id')->value('id');
        $dibuat = 0;

        foreach ($this->skenario() as $i => $s) {
            $kat = $kategori[$i % $kategori->count()];
            $kec = $kecamatan[$i % $kecamatan->count()];

            $report = $this->buatLaporan($i, $s, $kat, $kec);
            $this->buatRiwayat($report, $s, $petugas);
            $this->buatFoto($report, $s, $i);

            $dibuat++;
        }

        $this->command?->info("  {$dibuat} laporan contoh dibuat (awalan ".self::PREFIX.').');
    }

    /** Hapus HANYA data contoh — dikenali dari awalan kode. */
    public function purge(): int
    {
        $id = Report::withoutGlobalScopes()
            ->where('code', 'like', self::PREFIX.'-%')
            ->pluck('id');

        if ($id->isEmpty()) {
            return 0;
        }

        Attachment::whereIn('report_id', $id)->delete();
        Response::whereIn('report_id', $id)->delete();
        DB::table('nawasara_aspirations_supports')->whereIn('report_id', $id)->delete();
        Report::withoutGlobalScopes()->whereIn('id', $id)->delete();

        return $id->count();
    }

    private function buatLaporan(int $i, array $s, Category $kat, District $kec): Report
    {
        $masuk = now()->subDays($s['umur_hari'])->subHours($i % 24);

        // SLA dihitung dari jam kategori, sama seperti laporan sungguhan — bukan
        // angka tetap. Tenggat yang tak berhubungan dengan kategorinya membuat
        // kolom "Batas Waktu" tidak masuk akal saat diadu dengan halaman Kategori.
        //
        // ⚠️ Tenggat dihitung MAJU DARI SEKARANG untuk yang belum telat, bukan
        // dari tanggal masuk + jam kategori. Kategori ber-SLA pendek (72 jam)
        // yang dipasangkan ke laporan berumur 8 hari akan selalu jatuh tempo di
        // masa lalu, sehingga laporan yang ditandai belum telat tetap muncul di
        // saringan "lewat batas waktu" — dan sebaran contohnya jadi tidak
        // menggambarkan apa pun.
        $jam = (int) ($kat->sla_hours ?: 168);
        $jatuhTempo = $s['telat']
            ? now()->copy()->subHours(max(12, (int) ($jam / 4)))
            : now()->copy()->addHours(max(24, (int) ($jam / 2)));

        $sudahDikerjakan = in_array($s['status'], [
            Report::STATUS_AWAITING_VERIFICATION,
            Report::STATUS_RESOLVED,
        ], true);

        $report = new Report();
        $report->code = sprintf('%s-%s-%04d', self::PREFIX, $masuk->format('Y-m'), $i + 1);

        // Warga fiktif dipakai berulang supaya beberapa laporan berasal dari
        // orang yang sama — itu yang membuat riwayat "Laporan Saya" dan deteksi
        // ganda punya sesuatu untuk ditampilkan.
        $report->keycloak_sub = 'contoh-warga-'.($i % 6);

        $report->title = $s['judul'];
        $report->description = $s['isi'];
        $report->category_id = $kat->id;
        $report->opd_id = $kat->opd_id;
        $report->status = $s['status'];
        $report->is_anonymous = $s['anonim'] ?? false;

        $report->district_code = $kec->code;
        $report->district = $kec->name;
        $report->village = $s['desa'];
        $report->full_address = $s['alamat'].', Kec. '.$kec->name;

        // Sekitar Ponorogo (-7,87 / 111,47), digeser per laporan supaya peta di
        // Ringkasan tidak menumpuk jadi satu titik.
        $report->latitude = -7.8700 + (($i % 9) - 4) * 0.012;
        $report->longitude = 111.4700 + (($i % 7) - 3) * 0.014;
        $report->location_accuracy = 8 + ($i % 20);

        $report->received_at = $masuk;
        $report->created_at_device = $masuk->copy()->subMinutes(3);
        $report->response_due_at = $masuk->copy()->addHours(24);
        $report->sla_due_at = $jatuhTempo;
        $report->promised_sla_hours = $jam;

        // Laporan `submitted` belum pernah ditanggapi — itu memang artinya.
        // Tapi scopeOverdue() juga menghitung batas RESPONS (24 jam), jadi yang
        // berumur lebih dari sehari akan terhitung telat meski tenggat
        // penyelesaiannya masih jauh. Batas responsnya digeser mengikuti umur
        // laporan supaya hanya yang ditandai `telat` yang benar-benar telat.
        $report->first_responded_at = $s['status'] === Report::STATUS_SUBMITTED
            ? null
            : $masuk->copy()->addHours(4);

        if ($s['status'] === Report::STATUS_SUBMITTED && ! $s['telat']) {
            $report->response_due_at = now()->copy()->addHours(12);
        }

        $report->resolution_submitted_at = $sudahDikerjakan ? $masuk->copy()->addDays(2) : null;
        $report->escalation_level = $s['telat'] ? 1 : 0;
        $report->rating = $s['rating'] ?? null;

        // Terisi HANYA saat laporan selesai tanpa dinilai. Membedakan "warga
        // tidak menilai" dari "belum sempat menilai" adalah seluruh alasan kolom
        // ini dipisah dari `rating`.
        $report->rated_closed_at = ($s['status'] === Report::STATUS_RESOLVED && ($s['rating'] ?? null) === null)
            ? $masuk->copy()->addDays(10)
            : null;

        $report->support_count = $s['dukungan'] ?? 0;
        $report->created_at = $masuk;
        $report->updated_at = $masuk->copy()->addDays(1);
        $report->save();

        return $report;
    }

    private function buatRiwayat(Report $report, array $s, ?int $petugas): void
    {
        $masuk = $report->received_at;
        $langkah = [];

        if ($s['status'] !== Report::STATUS_SUBMITTED) {
            $langkah[] = [Report::STATUS_SUBMITTED, Report::STATUS_DISPATCHED,
                'Laporan diteruskan ke bidang terkait untuk ditindaklanjuti.', false, 3];
        }

        if (in_array($s['status'], [
            Report::STATUS_IN_PROGRESS,
            Report::STATUS_AWAITING_VERIFICATION,
            Report::STATUS_RESOLVED,
        ], true)) {
            $langkah[] = [Report::STATUS_DISPATCHED, Report::STATUS_IN_PROGRESS,
                'Sudah kami survei ke lokasi. Penanganan dijadwalkan pekan ini.', false, 20];

            // Catatan internal: tak pernah dibaca warga, dan justru bagian yang
            // menjelaskan kenapa sebuah laporan berhenti bergerak.
            $langkah[] = [null, null,
                'Material belum tersedia di gudang, menunggu pengadaan triwulan berikutnya.', true, 30];
        }

        if (in_array($s['status'], [
            Report::STATUS_AWAITING_VERIFICATION,
            Report::STATUS_RESOLVED,
        ], true)) {
            $langkah[] = [Report::STATUS_IN_PROGRESS, Report::STATUS_AWAITING_VERIFICATION,
                'Pekerjaan selesai, foto bukti terlampir. Mohon diperiksa.', false, 48];
        }

        if ($s['status'] === Report::STATUS_RESOLVED) {
            $langkah[] = [Report::STATUS_AWAITING_VERIFICATION, Report::STATUS_RESOLVED,
                'Hasil pekerjaan sudah sesuai. Laporan ditutup.', false, 72];
        }

        if ($s['status'] === Report::STATUS_REJECTED) {
            $langkah[] = [Report::STATUS_SUBMITTED, Report::STATUS_REJECTED,
                $s['alasan_tolak'] ?? 'Lokasi berada di luar wilayah Kabupaten Ponorogo.', false, 6];
        }

        foreach ($langkah as [$dari, $ke, $isi, $internal, $jamKe]) {
            $pada = $masuk->copy()->addHours($jamKe);

            Response::create([
                'report_id' => $report->id,
                'user_id' => $petugas,
                'status_from' => $dari,
                'status_to' => $ke,
                'body' => $isi,
                'is_internal' => $internal,
                'created_at' => $pada,
                'updated_at' => $pada,
            ]);
        }
    }

    private function buatFoto(Report $report, array $s, int $i): void
    {
        $masuk = $report->received_at;
        $jumlah = 1 + ($i % 2);

        for ($n = 1; $n <= $jumlah; $n++) {
            Attachment::create([
                'report_id' => $report->id,
                'kind' => Attachment::KIND_REPORT,
                'disk' => 'local',
                'bucket' => null,
                'path' => 'contoh-aspirations/laporan-'.$report->code.'-'.$n.'.jpg',
                'source' => $n === 1 ? Attachment::SOURCE_CAMERA : Attachment::SOURCE_GALLERY,

                // Sebagian tanpa EXIF — itu LUMRAH, karena WhatsApp membuangnya.
                // Kalau semua foto contoh punya waktu ambil, tampilan "Tanpa
                // waktu ambil" tidak pernah teruji.
                'captured_at' => $n === 1 ? $masuk->copy()->subMinutes(15) : null,
                'size' => 180000 + $i * 1337,
                'created_at' => $masuk,
                'updated_at' => $masuk,
            ]);
        }

        if (! in_array($report->status, [
            Report::STATUS_AWAITING_VERIFICATION,
            Report::STATUS_RESOLVED,
        ], true)) {
            return;
        }

        Attachment::create([
            'report_id' => $report->id,
            'kind' => Attachment::KIND_EVIDENCE,
            'disk' => 'local',
            'bucket' => null,
            'path' => 'contoh-aspirations/bukti-'.$report->code.'.jpg',
            'source' => Attachment::SOURCE_CAMERA,

            // Satu foto bukti sengaja berwaktu ambil SEBELUM laporannya masuk,
            // supaya penanda "Perlu dicek" (foto lama dipakai ulang) ikut
            // terlihat saat mengerjakan tampilan.
            'captured_at' => ($s['bukti_mencurigakan'] ?? false)
                ? $masuk->copy()->subDays(5)
                : $masuk->copy()->addDays(2),
            'size' => 240000 + $i * 911,
            'created_at' => $masuk->copy()->addDays(2),
            'updated_at' => $masuk->copy()->addDays(2),
        ]);
    }

    /**
     * Skenario laporan.
     *
     * Sebaran statusnya meniru antrean sungguhan: menumpuk di `in_progress`,
     * sedikit yang telat, sedikit yang ditolak.
     *
     * @return array<int, array<string, mixed>>
     */
    private function skenario(): array
    {
        return [
            ['status' => Report::STATUS_SUBMITTED, 'umur_hari' => 0, 'telat' => false,
                'judul' => 'Jalan berlubang di depan Pasar Legi',
                'isi' => "Sudah sebulan berlubang dan makin lebar setiap hujan.\n\nKemarin sore ada pengendara motor jatuh karena lubangnya tidak terlihat.",
                'desa' => 'Bangunsari', 'alamat' => 'Jl. Soekarno-Hatta depan Pasar Legi', 'dukungan' => 4],

            ['status' => Report::STATUS_SUBMITTED, 'umur_hari' => 1, 'telat' => false,
                'judul' => 'Lampu penerangan jalan mati sepanjang gang',
                'isi' => 'Sudah dua minggu gelap total. Warga khawatir karena banyak anak pulang mengaji lewat sini.',
                'desa' => 'Nologaten', 'alamat' => 'Gg. Melati RT 02 RW 05', 'dukungan' => 9],

            ['status' => Report::STATUS_DISPATCHED, 'umur_hari' => 3, 'telat' => false,
                'judul' => 'Sampah menumpuk di TPS tidak diangkut',
                'isi' => 'Sudah lima hari tidak diangkut, baunya sampai ke rumah warga dan mulai banyak lalat.',
                'desa' => 'Kertosari', 'alamat' => 'TPS Jl. Anjasmoro', 'dukungan' => 12],

            ['status' => Report::STATUS_DISPATCHED, 'umur_hari' => 4, 'telat' => false,
                'judul' => 'Saluran air tersumbat, jalan tergenang',
                'isi' => 'Setiap hujan air meluap ke badan jalan sampai setinggi mata kaki.',
                'desa' => 'Tonatan', 'alamat' => 'Jl. Batoro Katong depan SDN 2', 'dukungan' => 6, 'anonim' => true],

            ['status' => Report::STATUS_IN_PROGRESS, 'umur_hari' => 8, 'telat' => false,
                'judul' => 'Trotoar rusak membahayakan pejalan kaki',
                'isi' => 'Paving terangkat dan berlubang, sudah beberapa kali ada yang tersandung.',
                'desa' => 'Mangkujayan', 'alamat' => 'Jl. Jaksa Agung Suprapto', 'dukungan' => 3],

            ['status' => Report::STATUS_IN_PROGRESS, 'umur_hari' => 11, 'telat' => false,
                'judul' => 'Rambu lalu lintas roboh di perempatan',
                'isi' => 'Rambu larangan belok roboh sejak minggu lalu, kendaraan jadi sering salah arah.',
                'desa' => 'Bangunsari', 'alamat' => 'Perempatan Jl. Urip Sumoharjo', 'dukungan' => 2],

            ['status' => Report::STATUS_IN_PROGRESS, 'umur_hari' => 16, 'telat' => true,
                'judul' => 'Jembatan desa retak dan belum diperbaiki',
                'isi' => "Retakan makin lebar, sekarang sudah bisa dimasuki tangan.\n\nWarga takut jembatan ambruk karena tiap hari dilewati truk pasir.",
                'desa' => 'Sukorejo', 'alamat' => 'Jembatan Dusun Krajan', 'dukungan' => 18],

            ['status' => Report::STATUS_IN_PROGRESS, 'umur_hari' => 22, 'telat' => true,
                'judul' => 'Pipa air bocor sudah lama tidak ditangani',
                'isi' => 'Air terbuang percuma sepanjang hari dan jalan jadi becek terus.',
                'desa' => 'Ronowijayan', 'alamat' => 'Jl. Ki Ageng Kutu RT 01', 'dukungan' => 7],

            ['status' => Report::STATUS_AWAITING_VERIFICATION, 'umur_hari' => 13, 'telat' => false,
                'judul' => 'Perbaikan atap ruang kelas sekolah dasar',
                'isi' => 'Atap ruang kelas 3 bocor saat hujan, siswa terpaksa pindah ruangan.',
                'desa' => 'Josari', 'alamat' => 'SDN Josari 1', 'dukungan' => 5, 'bukti_mencurigakan' => true],

            ['status' => Report::STATUS_AWAITING_VERIFICATION, 'umur_hari' => 18, 'telat' => false,
                'judul' => 'Posyandu kekurangan alat timbang bayi',
                'isi' => 'Timbangan rusak sejak bulan lalu, kader terpaksa meminjam ke desa sebelah.',
                'desa' => 'Ngrupit', 'alamat' => 'Posyandu Mawar Dusun Tengah', 'dukungan' => 1],

            ['status' => Report::STATUS_RESOLVED, 'umur_hari' => 26, 'telat' => false,
                'judul' => 'Pohon tumbang menutup separuh jalan',
                'isi' => 'Pohon besar tumbang setelah angin kencang, kendaraan tidak bisa lewat.',
                'desa' => 'Pulung', 'alamat' => 'Jl. Raya Pulung KM 4', 'rating' => 5, 'dukungan' => 15],

            ['status' => Report::STATUS_RESOLVED, 'umur_hari' => 34, 'telat' => false,
                'judul' => 'Jalan usaha tani rusak menghambat panen',
                'isi' => 'Petani kesulitan mengangkut hasil panen karena jalan berlumpur dan berlubang.',
                'desa' => 'Sambit', 'alamat' => 'Jalan usaha tani Blok C', 'rating' => 4, 'dukungan' => 11],

            ['status' => Report::STATUS_RESOLVED, 'umur_hari' => 41, 'telat' => false,
                'judul' => 'Papan penunjuk arah wisata rusak',
                'isi' => 'Papan penunjuk arah ke air terjun sudah pudar dan miring.',
                'desa' => 'Ngebel', 'alamat' => 'Simpang menuju Telaga Ngebel', 'rating' => 3, 'dukungan' => 2],

            // Selesai TANPA dinilai — masa penilaian ditutup. Tampil berbeda
            // dari "menunggu penilaian", dan perbedaan itu yang perlu terlihat.

            ['status' => Report::STATUS_RESOLVED, 'umur_hari' => 52, 'telat' => false,
                'judul' => 'Selokan mampet di depan puskesmas',
                'isi' => 'Air tergenang dan berbau tepat di depan pintu masuk puskesmas.',
                'desa' => 'Siman', 'alamat' => 'Jl. Puskesmas Siman', 'dukungan' => 3],

            ['status' => Report::STATUS_REJECTED, 'umur_hari' => 29, 'telat' => false,
                'judul' => 'Jalan provinsi rusak parah',
                'isi' => 'Jalan berlubang besar sepanjang beberapa kilometer.',
                'desa' => 'Babadan', 'alamat' => 'Jl. Raya Madiun KM 12', 'anonim' => true, 'alasan_tolak' => 'Ruas ini kewenangan Pemerintah Provinsi. Laporan sudah kami teruskan ke Dinas PU Provinsi Jawa Timur.'],

            ['status' => Report::STATUS_REJECTED, 'umur_hari' => 45, 'telat' => false,
                'judul' => 'Keluhan tetangga memutar musik keras',
                'isi' => 'Setiap malam memutar musik sampai larut.',
                'desa' => 'Jenangan', 'alamat' => 'Perum Griya Asri Blok B', 'alasan_tolak' => 'Perselisihan antarwarga ditangani lebih dulu melalui RT/RW dan kepala desa.'],
        ];
    }
}
