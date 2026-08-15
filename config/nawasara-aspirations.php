<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kategori awal (seed)
    |--------------------------------------------------------------------------
    |
    | 13 urusan hasil rapat 13 Agustus 2026 (aset/data/URUSAN_DINAS.xlsx di
    | repo GO). Ini ISI AWAL, bukan daftar mati — setelah di-seed, kategori
    | dikelola lewat panel dan boleh ditambah/diubah/dinonaktifkan tanpa
    | menyentuh berkas ini.
    |
    | `opd_code` dicocokkan ke `nawasara_registry_opd.code` saat seed —
    | HURUF BESAR, mengikuti kode yang sudah ada di registry. Polanya tidak
    | seragam (`DPUPKP` vs `DINAS_KESEHATAN`) karena registry diisi bertahap;
    | nilai di bawah disalin apa adanya dari sana, bukan ditebak. Bila
    | tidak ketemu, kategori tetap dibuat dengan opd_id NULL dan seeder
    | melaporkannya — laporan pada kategori itu tidak dapat didisposisi
    | otomatis sampai OPD-nya terdaftar.
    |
    | ⚠️ `sla_hours` di bawah masih ANGKA SEMENTARA. Hasil rapat menetapkan
    | urusan dan dinas, TIDAK menetapkan batas waktu. Angka ini diturunkan dari
    | daftar lama (03 §4) agar sistem dapat berjalan, dan WAJIB diganti dengan
    | hasil kesepakatan OPD sebelum warga memakainya — ini janji yang
    | ditampilkan sebelum mengirim laporan.
    |
    */
    'categories' => [
        [
            'code' => 'infrastruktur',
            'name' => 'Infrastruktur',
            'hint' => 'Jalan berlubang, trotoar lepas, drainase tersumbat, jembatan antar-dusun rusak, akses jalan sekolah berbahaya',
            'icon_name' => 'construction',
            'color' => 'stone',
            'opd_code' => 'DPUPKP',
            'sla_hours' => 336,   // 14 hari — SEMENTARA
        ],
        [
            'code' => 'lingkungan',
            'name' => 'Lingkungan Hidup',
            'hint' => 'Sampah menumpuk, pohon rawan tumbang, taman rusak, pembakaran sampah, limbah mencemari',
            'icon_name' => 'trees',
            'color' => 'lime',
            'opd_code' => 'DLH',
            'sla_hours' => 72,    // 3 hari — SEMENTARA
        ],
        [
            'code' => 'ketertiban',
            'name' => 'Ketertiban',
            'hint' => 'PKL menghalangi trotoar, iklan ilegal, sarang tawon, ular masuk permukiman, balap liar, sound system berlebih',
            'icon_name' => 'shield-alert',
            'color' => 'red',
            'opd_code' => 'SATPOL_PP',
            'sla_hours' => 72,    // 3 hari — SEMENTARA. Lihat catatan bencana.
        ],
        [
            'code' => 'perhubungan',
            'name' => 'Perhubungan',
            'hint' => 'Rambu roboh, lampu bangjo eror, marka pudar, parkir liar, truk ODOL, angkutan sekolah, PJU',
            'icon_name' => 'traffic-cone',
            'color' => 'orange',
            'opd_code' => 'DINAS_PERHUBUNGAN',
            'sla_hours' => 168,   // 7 hari — SEMENTARA
        ],
        [
            'code' => 'pariwisata',
            'name' => 'Budaya & Pariwisata',
            'hint' => 'Vandalisme cagar budaya, pungli tiket wisata, fasilitas wisata rusak',
            'icon_name' => 'landmark',
            'color' => 'violet',
            'opd_code' => 'DISBUDPARPORA',
            'sla_hours' => 336,   // 14 hari — SEMENTARA
        ],
        [
            'code' => 'pertanian',
            'name' => 'Pertanian',
            'hint' => 'Irigasi mampet, pupuk subsidi langka atau di atas HET, serangan hama',
            'icon_name' => 'wheat',
            'color' => 'green',
            'opd_code' => 'DINAS_PERTANIAN_KET',
            'sla_hours' => 336,   // 14 hari — SEMENTARA
        ],
        [
            'code' => 'kesehatan',
            'name' => 'Kesehatan',
            'hint' => 'Layanan Puskesmas atau Posyandu, stok obat habis, jentik nyamuk, bau limbah ternak',
            'icon_name' => 'stethoscope',
            'color' => 'rose',
            'opd_code' => 'DINAS_KESEHATAN',
            'sla_hours' => 168,   // 7 hari — SEMENTARA
        ],
        [
            'code' => 'pendidikan',
            'name' => 'Pendidikan',
            'hint' => 'Pungutan liar sekolah, atap kelas bocor, bangku atau toilet sekolah rusak',
            'icon_name' => 'graduation-cap',
            'color' => 'indigo',
            'opd_code' => 'DINAS_PENDIDIKAN',
            'sla_hours' => 336,   // 14 hari — SEMENTARA
        ],
        [
            'code' => 'sosial',
            'name' => 'Sosial',
            'hint' => 'Bansos salah sasaran, lansia telantar, warga sebatang kara, disabilitas butuh penanganan',
            'icon_name' => 'heart-handshake',
            'color' => 'teal',
            'opd_code' => 'DINAS_SOSIAL_PEMBER',
            'sla_hours' => 336,   // 14 hari — SEMENTARA
        ],
        [
            'code' => 'tenaga-kerja',
            'name' => 'Tenaga Kerja',
            'hint' => 'Aduan perlindungan PMI, agensi nakal, masalah keberangkatan',
            'icon_name' => 'briefcase',
            'color' => 'amber',
            'opd_code' => 'DINAS_TENAGA_KERJA',
            'sla_hours' => 336,   // 14 hari — SEMENTARA
            // Hasilnya berkas pemeriksaan/pendampingan, bukan sesuatu yang
            // dapat dipotret — lihat migrasi requires_evidence.
            'requires_evidence' => false,
        ],
        [
            'code' => 'perdagangan',
            'name' => 'Perdagangan & UMKM',
            'hint' => 'Sarana pasar rusak, gas elpiji 3 kg langka atau di atas HET',
            'icon_name' => 'store',
            'color' => 'yellow',
            'opd_code' => 'PERDAGKUM',
            'sla_hours' => 168,   // 7 hari — SEMENTARA
        ],
        [
            'code' => 'pemerintahan',
            'name' => 'Pemerintahan',
            'hint' => 'Pungli dan gratifikasi, petugas tidak ada di tempat, pelayanan kasar atau diskriminatif',
            'icon_name' => 'gavel',
            'color' => 'slate',
            'opd_code' => 'INSPEKTORAT',
            'sla_hours' => 336,   // 14 hari — SEMENTARA
            // Laporan di sini TIDAK boleh didisposisi ke OPD yang dilaporkan (#9).
            'is_sensitive' => true,
            // Hasilnya berkas pemeriksaan/pendampingan, bukan sesuatu yang
            // dapat dipotret — lihat migrasi requires_evidence.
            'requires_evidence' => false,
        ],
        [
            'code' => 'kominfo',
            'name' => 'Komunikasi & Informatika',
            'hint' => 'WiFi publik mati atau lambat, layanan online Pemkab eror, kabel fiber optik semrawut',
            'icon_name' => 'wifi',
            'color' => 'sky',
            'opd_code' => 'KOMINFO',
            'sla_hours' => 168,   // 7 hari — SEMENTARA
        ],
        [
            'code' => 'aspirasi',
            'name' => 'Aspirasi & Usulan',
            'hint' => 'Usulan pembangunan, ide untuk daerah, masukan kebijakan',
            'icon_name' => 'message-square',
            'color' => 'fuchsia',
            'opd_code' => 'BAPPEDA',
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
    */
    'sla' => [
        // Batas TANGGAPAN PERTAMA, terpisah dari batas selesai. Inilah yang
        // paling terasa bagi warga: jalan berlubang wajar selesai 14 hari,
        // tetapi warga yang 14 hari tidak mendengar apa pun akan menyimpulkan
        // laporannya diabaikan lalu berhenti melapor.
        'response_hours' => (int) env('ASPIRATIONS_RESPONSE_HOURS', 72),

        // Batas Kabid memverifikasi setelah pekerjaan diserahkan. Terlalu
        // longgar: warga menunggu tanpa sebab. Terlalu ketat: Kabid menyetujui
        // asal-asalan.
        'verification_hours' => (int) env('ASPIRATIONS_VERIFICATION_HOURS', 48),
    ],

    /*
    |--------------------------------------------------------------------------
    | Batas & aturan
    |--------------------------------------------------------------------------
    */
    'limits' => [
        // Maksimal laporan per warga per hari (#12). Dikunci pada keycloak_sub,
        // jadi tetap berlaku untuk laporan anonim — sistem tahu pengirimnya.
        'reports_per_day' => (int) env('ASPIRATIONS_REPORTS_PER_DAY', 5),

        // Foto per laporan. Divalidasi di SERVER, bukan hanya di aplikasi.
        'photos_per_report' => (int) env('ASPIRATIONS_PHOTOS_PER_REPORT', 3),

        // Ukuran maksimal per foto (KB) setelah aplikasi mengompresi.
        'photo_max_kb' => (int) env('ASPIRATIONS_PHOTO_MAX_KB', 2048),

        'description_max' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Deteksi laporan ganda (#11)
    |--------------------------------------------------------------------------
    */
    'duplicate' => [
        'radius_meters' => (int) env('ASPIRATIONS_DUPLICATE_RADIUS', 50),
        'window_days' => (int) env('ASPIRATIONS_DUPLICATE_DAYS', 7),
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
        'disk' => env('ASPIRATIONS_DISK', 'minio'),

        // Umur URL presigned (detik). Pendek dengan sengaja.
        'url_ttl' => (int) env('ASPIRATIONS_URL_TTL', 900),
    ],

    /*
    |--------------------------------------------------------------------------
    | Penilaian warga
    |--------------------------------------------------------------------------
    */
    'rating' => [
        // Tanpa penilaian sekian hari → selesai otomatis (#7).
        'auto_close_days' => (int) env('ASPIRATIONS_AUTO_CLOSE_DAYS', 7),

        // Nilai <= ini membuka kembali laporan (#6).
        'reopen_threshold' => (int) env('ASPIRATIONS_REOPEN_THRESHOLD', 2),
    ],

    'scheduler' => [
        'enabled' => env('ASPIRATIONS_SCHEDULER_ENABLED', true),
    ],
];
