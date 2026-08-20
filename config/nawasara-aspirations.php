<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kategori awal (seed)
    |--------------------------------------------------------------------------
    |
    | 13 urusan hasil rapat 13 Agustus 2026 (aset/data/URUSAN_DINAS.xlsx di
    | repo GO). Ini ISI AWAL, bukan daftar mati â€” setelah di-seed, kategori
    | dikelola lewat panel dan boleh ditambah/diubah/dinonaktifkan tanpa
    | menyentuh berkas ini.
    |
    | `opd_code` dicocokkan ke `nawasara_registry_opd.code` saat seed â€”
    | HURUF BESAR, mengikuti kode yang sudah ada di registry. Polanya tidak
    | seragam (`DPUPKP` vs `DINAS_KESEHATAN`) karena registry diisi bertahap;
    | nilai di bawah disalin apa adanya dari sana, bukan ditebak. Bila
    | tidak ketemu, kategori tetap dibuat dengan opd_id NULL dan seeder
    | melaporkannya â€” laporan pada kategori itu tidak dapat didisposisi
    | otomatis sampai OPD-nya terdaftar.
    |
    | âš ï¸ `sla_hours` di bawah masih ANGKA SEMENTARA. Hasil rapat menetapkan
    | urusan dan dinas, TIDAK menetapkan batas waktu. Angka ini diturunkan dari
    | daftar lama (03 Â§4) agar sistem dapat berjalan, dan WAJIB diganti dengan
    | hasil kesepakatan OPD sebelum warga memakainya â€” ini janji yang
    | ditampilkan sebelum mengirim laporan.
    |
    */
    'categories' => [
        [
            'code' => 'jalan',
            'name' => 'Jalan & Jembatan',
            'hint' => 'Jalan berlubang, trotoar lepas, median rusak, jembatan antar-dusun putus, akses jalan sekolah berbahaya',
            'icon_name' => 'route',
            'color' => 'stone',
            'opd_code' => 'DPUPKP',
            'sla_hours' => 336,   // 14 hari — SEMENTARA
        ],
        [
            'code' => 'lampu',
            'name' => 'Penerangan Jalan',
            'hint' => 'Lampu penerangan jalan umum (PJU) mati, tiang miring, kabel menjuntai',
            'icon_name' => 'lightbulb',
            'color' => 'amber',
            // PJU diampu DISHUB, bukan DPUPKP — ditegaskan URUSAN_DINAS.xlsx.
            'opd_code' => 'DINAS_PERHUBUNGAN',
            'sla_hours' => 168,   // 7 hari — SEMENTARA
        ],
        [
            'code' => 'sampah',
            'name' => 'Sampah & Lingkungan',
            'hint' => 'Sampah menumpuk, TPS terlambat diangkut, limbah mencemari, pohon rawan tumbang, taman kota rusak',
            'icon_name' => 'trash-2',
            'color' => 'lime',
            'opd_code' => 'DLH',
            'sla_hours' => 168,   // 7 hari — SEMENTARA
        ],
        [
            'code' => 'air',
            'name' => 'Air & Drainase',
            'hint' => 'Drainase kota tersumbat, selokan permukiman mampet, tanggul jebol, irigasi sawah mampet',
            'icon_name' => 'droplets',
            'color' => 'sky',
            // Terbagi DPUPKP (kota), DLH (permukiman), DISTANI (irigasi sawah).
            // Disposisi halusnya ditentukan Admin Kabupaten dari titik lokasi.
            'opd_code' => 'DPUPKP',
            'sla_hours' => 168,   // 7 hari — SEMENTARA
        ],
        [
            'code' => 'ketertiban',
            'name' => 'Ketertiban Umum',
            'hint' => 'Balap liar, sound horeg, PKL menghalangi trotoar, iklan dipaku di pohon, sarang tawon, ular masuk rumah',
            'icon_name' => 'shield-alert',
            'color' => 'red',
            'opd_code' => 'SATPOLPP',
            'sla_hours' => 72,    // 3 hari — SEMENTARA
        ],
        [
            'code' => 'lalulintas',
            'name' => 'Lalu Lintas',
            'hint' => 'Rambu roboh, bangjo (APILL) eror, marka pudar, parkir liar, truk ODOL',
            'icon_name' => 'traffic-cone',
            'color' => 'orange',
            'opd_code' => 'DINAS_PERHUBUNGAN',
            'sla_hours' => 168,   // 7 hari — SEMENTARA
        ],
        [
            'code' => 'kesehatan',
            'name' => 'Kesehatan',
            'hint' => 'Layanan puskesmas, stok obat habis, sarang jentik nyamuk, bau limbah ternak',
            'icon_name' => 'stethoscope',
            'color' => 'rose',
            'opd_code' => 'DINAS_KESEHATAN',
            'sla_hours' => 168,   // 7 hari — SEMENTARA
        ],
        [
            'code' => 'pendidikan',
            'name' => 'Pendidikan',
            'hint' => 'Pungutan sekolah tak wajar, atap kelas bocor, toilet sekolah rusak',
            'icon_name' => 'graduation-cap',
            'color' => 'indigo',
            'opd_code' => 'DINAS_PENDIDIKAN',
            'sla_hours' => 336,   // 14 hari — SEMENTARA
        ],
        [
            'code' => 'pertanian',
            'name' => 'Pertanian',
            'hint' => 'Pupuk subsidi langka atau mahal, serangan hama skala desa',
            'icon_name' => 'wheat',
            'color' => 'green',
            'opd_code' => 'DINAS_PERTANIAN_KET',
            'sla_hours' => 336,   // 14 hari — SEMENTARA
        ],
        [
            'code' => 'wisata',
            'name' => 'Wisata & Cagar Budaya',
            'hint' => 'Pungli tiket wisata, vandalisme situs bersejarah, fasilitas wisata rusak',
            'icon_name' => 'ferris-wheel',
            'color' => 'purple',
            'opd_code' => 'DISBUDPARPORA',
            'sla_hours' => 336,   // 14 hari — SEMENTARA
        ],
        [
            'code' => 'digital',
            'name' => 'Layanan Digital',
            'hint' => 'WiFi publik mati, layanan online eror, kabel fiber optic semrawut',
            'icon_name' => 'router',
            'color' => 'blue',
            'opd_code' => 'KOMINFO',
            'sla_hours' => 168,   // 7 hari — SEMENTARA
        ],
        [
            'code' => 'perdagangan',
            'name' => 'Perdagangan',
            'hint' => 'Elpiji 3 kg langka atau mahal, sarana pasar rusak',
            'icon_name' => 'shopping-basket',
            'color' => 'yellow',
            'opd_code' => 'PERDAGKUM',
            'sla_hours' => 168,   // 7 hari — SEMENTARA
        ],
        [
            'code' => 'kerja',
            'name' => 'Ketenagakerjaan',
            'hint' => 'Agensi PMI nakal, kasus pekerja migran di luar negeri',
            'icon_name' => 'hard-hat',
            'color' => 'slate',
            'opd_code' => 'DISNAKER',
            'sla_hours' => 336,   // 14 hari — SEMENTARA
        ],
        [
            'code' => 'pelayanan',
            'name' => 'Pelayanan Publik',
            'hint' => 'Dugaan pungli, petugas tidak di tempat, dilayani kasar',
            'icon_name' => 'landmark',
            'color' => 'violet',
            'opd_code' => 'INSPEKTORAT',
            'sla_hours' => 336,   // 14 hari — SEMENTARA
            // Laporan pungli menyangkut pihak yang dilaporkan; ditandai
            // sensitif supaya tidak tampil di peta publik.
            'is_sensitive' => true,
        ],
        [
            'code' => 'sosial',
            'name' => 'Sosial',
            'hint' => 'Bansos salah sasaran, lansia terlantar, warga disabilitas',
            'icon_name' => 'heart-handshake',
            'color' => 'teal',
            'opd_code' => 'DINAS_SOSIAL_PEMBER',
            'sla_hours' => 336,   // 14 hari — SEMENTARA
        ],
        [
            'code' => 'bencana',
            'name' => 'Bencana',
            'hint' => 'Pohon sudah tumbang menutup jalan, tanah longsor, banjir, kebakaran',
            'icon_name' => 'waves',
            'color' => 'cyan',
            // ⚠️ BPBD BELUM TERDAFTAR di registry (diperiksa 19 Agustus
            // 2026). Kategori ini tetap dibuat, tetapi opd_id-nya NULL dan
            // laporan bencana TIDAK dapat didisposisi otomatis — padahal
            // justru kategori inilah yang paling menuntut kecepatan (SLA 3
            // jam). Daftarkan BPBD di registry, lalu seed ulang.
            'opd_code' => 'BPBD',
            'sla_hours' => 3,     // 3 jam — SEMENTARA
        ],
        [
            'code' => 'aspirasi',
            'name' => 'Aspirasi & Usulan',
            'hint' => 'Usulan pembangunan, masukan kebijakan, ide untuk desa',
            'icon_name' => 'message-square',
            'color' => 'fuchsia',
            // Registry lokal memakai BAPPEDA, produksi BAPPERIDA — badan yang
            // sama, nomenklaturnya berganti. Keduanya dicoba berurutan.
            'opd_code' => ['BAPPERIDA', 'BAPPEDA'],
            'sla_hours' => 720,   // 30 hari — SEMENTARA
            // Aspirasi dijawab dengan tanggapan tertulis; tidak ada pekerjaan
            // fisik yang dapat difoto.
            'requires_evidence' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SLA
    |--------------------------------------------------------------------------
    |
    | ⚠️ Angka pada blok `sla`, `limits`, dan `duplicate` hanyalah NILAI AWAL.
    | Yang berlaku dibaca dari tabel setting lewat Support\Settings, diubah
    | admin di halaman Pengaturan Lapor. Nilai di sini dipakai hanya sebelum
    | admin pernah menyentuhnya, dan sebagai penjaga bila baris setting-nya
    | terhapus.
    |
    | Tidak lagi memakai env(): nilainya sudah dapat diubah dari panel, dan
    | menyediakan jalur kedua lewat .env berarti ada dua tempat yang harus
    | diperiksa saat angka yang berlaku ternyata bukan yang diduga.
    |
    */
    'sla' => [
        // Batas TANGGAPAN PERTAMA, terpisah dari batas selesai. Inilah yang
        // paling terasa bagi warga: jalan berlubang wajar selesai 14 hari,
        // tetapi warga yang 14 hari tidak mendengar apa pun akan menyimpulkan
        // laporannya diabaikan lalu berhenti melapor.
        'response_hours' => 72,

        // Batas Kabid memverifikasi setelah pekerjaan diserahkan. Terlalu
        // longgar: warga menunggu tanpa sebab. Terlalu ketat: Kabid menyetujui
        // asal-asalan.
        'verification_hours' => 48,
    ],

    /*
    |--------------------------------------------------------------------------
    | Batas & aturan
    |--------------------------------------------------------------------------
    */
    'limits' => [
        // Maksimal laporan per warga per hari (#12). Dikunci pada keycloak_sub,
        // jadi tetap berlaku untuk laporan anonim â€” sistem tahu pengirimnya.
        'reports_per_day' => 5,

        // Foto per laporan. Divalidasi di SERVER, bukan hanya di aplikasi.
        'photos_per_report' => 3,

        // Ukuran maksimal per foto (KB) setelah aplikasi mengompresi.
        'photo_max_kb' => 2048,

        'description_max' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Deteksi laporan ganda (#11)
    |--------------------------------------------------------------------------
    */
    'duplicate' => [
        'radius_meters' => 50,
        'window_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Penyimpanan berkas
    |--------------------------------------------------------------------------
    */
    'storage' => [
        // MinIO lewat disk S3. Bucket PRIVAT — foto warga memuat wajah, pelat
        // nomor, dan bagian dalam rumah; bucket public-read berarti siapa pun
        // yang menebak kunci dapat mengunduhnya dan mesin pencari akan
        // mengindeksnya.
        //
        // KREDENSIAL SERVER ADA DI VAULT (group `minio`), bukan di sini dan
        // bukan di .env — admin dapat menggantinya lewat panel saat kunci
        // bocor, tanpa akses server dan tanpa deploy ulang.
        //
        // Tiga nilai di bawah BUKAN rahasia dan tidak berbeda antar lingkungan,
        // jadi ditulis apa adanya. Membungkusnya dengan env() hanya menambah
        // satu tempat lagi yang harus dicari orang saat nilainya perlu diubah,
        // tanpa memberi keleluasaan yang benar-benar dipakai.
        'disk' => 'minio',

        // Bucket khusus paket ini. Satu server MinIO melayani banyak bucket
        // dengan kunci yang sama; memisahkan bucket per paket membuat foto
        // warga tidak bercampur dengan berkas paket lain, dan memudahkan
        // menerapkan aturan penyimpanan (retensi, versioning) yang berbeda.
        //
        // Dikosongkan (null) berarti memakai bucket bawaan dari Vault.
        'bucket' => 'nawasara-aspirations',

        // Umur URL presigned (detik). Pendek dengan sengaja.
        'url_ttl' => 900,
    ],

    /*
    |--------------------------------------------------------------------------
    | Penilaian warga
    |--------------------------------------------------------------------------
    */
    'rating' => [
        // Tanpa penilaian sekian hari â†’ selesai otomatis (#7).
        'auto_close_days' => 7,

        // Nilai <= ini membuka kembali laporan (#6).
        'reopen_threshold' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Geocoding
    |--------------------------------------------------------------------------
    |
    | Bawaannya `null` â€” sistem berjalan penuh TANPA kunci Google. Laporan
    | tetap masuk dan tetap ditangani; hanya kolom wilayah yang kosong.
    |
    | Dibuat begitu dengan sengaja: sistem yang menuntut kunci pihak ketiga
    | untuk sekadar menerima laporan warga akan mati begitu tagihan telat
    | dibayar atau kuota habis.
    |
    | Setel ke 'google' dan isi kuncinya bila sudah tersedia.
    */
    'geocoding' => [
        'provider' => env('ASPIRATIONS_GEOCODER', 'null'),

        // âš ï¸ Kunci ini TIDAK PERNAH boleh sampai ke aplikasi ponsel. Kunci
        // yang tertanam di APK dapat dibongkar dan dipakai orang lain atas
        // tagihan Pemkab â€” itulah sebabnya geocoding dikerjakan di server.
        'google_key' => env('ASPIRATIONS_GOOGLE_MAPS_KEY', ''),
    ],

    'scheduler' => [
        'enabled' => env('ASPIRATIONS_SCHEDULER_ENABLED', true),
    ],
];
