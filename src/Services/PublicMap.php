<?php

namespace Nawasara\Aspirations\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Nawasara\Aspirations\Models\Attachment;
use Nawasara\Aspirations\Models\District;
use Nawasara\Aspirations\Models\Report;

/**
 * Laporan yang boleh dilihat SIAPA PUN — termasuk yang belum punya akun.
 *
 * ⚠️ Seluruh penyaringan privasi hidup di sini, pada satu query builder yang
 * dipakai kedua bentuk jawaban. Menyalin syaratnya ke tiap aksi berarti suatu
 * hari salah satu salinan tertinggal saat aturannya berubah — dan yang bocor
 * bukan data biasa, melainkan laporan dugaan pungli beserta lokasinya.
 *
 * Tiga hal disaring, masing-masing punya alasan yang berbeda:
 *
 *   kategori sensitif  Laporan pungli menyangkut pihak yang dilaporkan.
 *                      Sekali terlihat sekampung, warga berhenti
 *                      melaporkannya — dan justru kategori itu yang paling
 *                      bernilai bagi pimpinan.
 *   laporan ditandai   `flagged_at` berarti isinya sedang diragukan. Belum
 *                      tentu salah, tetapi menampilkannya ke publik sebelum
 *                      diperiksa membuat sistem ini ikut menyebarkannya.
 *   tanpa kecamatan    Tidak dapat ditaruh di peta, dan menampilkannya di
 *                      daftar tanpa wilayah membingungkan.
 */
class PublicMap
{
    /**
     * Dasar SEMUA jawaban peta. Tidak ada jalur lain ke data publik.
     */
    /**
     * Satu laporan yang boleh dibuka warga LAIN, atau null.
     *
     * Lewat `visible()` yang sama dengan peta, bukan query sendiri: kategori
     * sensitif, laporan yang ditandai, dan yang tanpa kecamatan dijawab sama
     * seperti laporan yang tidak ada. Satu-satunya jalan ke data publik tetap
     * satu, jadi kategori yang kelak ditandai sensitif langsung ikut
     * terlindungi di peta maupun di detail.
     */
    public function findVisible(string $code): ?Report
    {
        return $this->visible()->where('code', $code)->first();
    }

    /**
     * Sisakan hanya laporan yang boleh dilihat warga lain.
     *
     * Untuk tempat yang mendapat laporan lewat jalan lain, misalnya pencarian
     * laporan serupa berdasarkan jarak. Kelayakannya ditanyakan ke `visible()`
     * yang sama, bukan diperiksa ulang di sini, supaya aturan publik tetap
     * hanya ada di satu tempat.
     *
     * @param  \Illuminate\Support\Collection<int, Report>  $reports
     * @return \Illuminate\Support\Collection<int, Report>
     */
    public function onlyVisible(\Illuminate\Support\Collection $reports): \Illuminate\Support\Collection
    {
        if ($reports->isEmpty()) {
            return $reports;
        }

        $allowed = $this->visible()
            ->whereIn('id', $reports->map(fn (Report $r) => $r->getKey())->all())
            ->pluck('id')
            ->flip();

        return $reports->filter(fn (Report $r) => $allowed->has($r->getKey()))->values();
    }

    protected function visible(): Builder
    {
        return Report::query()
            // Kategori sensitif tidak pernah keluar. Dikerjakan lewat
            // whereHas, bukan daftar id yang disalin — supaya kategori baru
            // yang ditandai sensitif langsung terlindungi tanpa mengubah
            // kode di sini.
            ->whereHas('category', fn ($q) => $q->where('is_sensitive', false))
            ->whereNull('flagged_at')
            ->whereNotNull('district_code');
    }

    /**
     * Sebaran per kecamatan — satu baris per kecamatan yang punya laporan.
     *
     * Kecamatan tanpa laporan sengaja TIDAK dikirim: peta menampilkan tempat
     * yang punya sesuatu untuk dilihat, dan dua puluh satu penanda bernilai
     * nol hanya memenuhi layar.
     *
     * @param  array<string,mixed>  $filters  category (kode), status
     * @return array<int,array<string,mixed>>
     */
    public function districts(array $filters = []): array
    {
        $rows = $this->applyFilters($this->visible(), $filters)
            ->selectRaw('district_code, COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as resolved', [Report::STATUS_RESOLVED])
            ->groupBy('district_code')
            ->get()
            ->keyBy('district_code');

        if ($rows->isEmpty()) {
            return [];
        }

        // Status terbanyak per kecamatan, untuk warna penanda. Diambil
        // terpisah karena "modus" tidak dapat dihitung dalam satu agregat
        // yang portabel antar-basis-data.
        $dominant = $this->dominantStatuses(array_keys($rows->all()), $filters);

        return District::whereIn('code', $rows->keys())
            ->orderBy('name')
            ->get()
            ->map(function (District $d) use ($rows, $dominant) {
                $row = $rows[$d->code];

                return [
                    'district_code' => $d->code,
                    'district_name' => $d->name,
                    'total' => (int) $row->total,
                    'resolved' => (int) $row->resolved,
                    'dominant_status' => $dominant[$d->code] ?? null,
                    'latitude' => $d->latitude,
                    'longitude' => $d->longitude,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Laporan di satu kecamatan, berpaginasi 20 seperti `GET /reports`.
     */
    public function reportsIn(string $districtCode, array $filters = []): LengthAwarePaginator
    {
        return $this->applyFilters($this->visible(), $filters)
            ->where('district_code', $districtCode)
            // Relasi dimuat di depan — tanpa ini tiap baris memicu kueri
            // kategorinya sendiri, dua puluh satu kueri untuk satu halaman.
            ->with(['category', 'opd'])
            ->latest('received_at')
            ->paginate(20);
    }

    /**
     * Laporan terdekat dari posisi warga — panel "Laporan di sekitar Anda".
     *
     * ⚠️ Koordinat laporan TIDAK PERNAH ikut keluar. Yang dikirim hanya jarak
     * yang sudah dibulatkan, dan pembulatan itu pengaman, bukan kerapian:
     * jarak yang tepat dari tiga posisi berbeda dapat dipakai menghitung balik
     * titik laporan (trilaterasi) — persis yang hendak dilindungi dengan tidak
     * mengirimkan koordinatnya. Lihat [self::roundDistance].
     *
     * Laporan tanpa koordinat sengaja tetap ikut, dengan jarak `null`. Sebagian
     * besar laporan lama tidak punya titik, dan membuangnya membuat panel ini
     * terlihat kosong padahal datanya ada.
     *
     * @param  array<string,mixed>  $filters  category, status
     */
    public function reportsNear(
        float $latitude,
        float $longitude,
        ?int $radiusMeters = null,
        array $filters = []
    ): LengthAwarePaginator {
        $query = $this->applyFilters($this->visible(), $filters)
            ->with(['category', 'opd']);

        $this->withDistance($query, $latitude, $longitude, $radiusMeters);

        // Yang punya jarak lebih dulu, terdekat di atas — itu yang dicari warga
        // saat membuka panel ini. Yang tanpa koordinat jatuh ke bawah, urut
        // terbaru, bukan tercampur acak di tengah daftar.
        return $query
            ->orderByRaw('distance_meters IS NULL')
            ->orderByRaw('distance_meters ASC')
            ->latest('received_at')
            ->paginate(20);
    }

    /** Urutan yang diterima linimasa. Sengaja tanpa "terpopuler"; lihat feed(). */
    public const FEED_SORTS = ['nearest', 'latest', 'recently_resolved'];

    /**
     * Linimasa laporan warga: daftar yang digulir, dengan foto.
     *
     * Lewat `visible()` yang sama dengan peta dan detail publik. Penyaringan
     * dan pengurutan terjadi di basis data SEBELUM paginasi, supaya halaman
     * kedua berisi data yang benar.
     *
     * ⚠️ Tidak ada urutan "terpopuler" (support_count), dengan sengaja. Urutan
     * populer mengundang warga menulis untuk dilihat, bukan untuk ditangani,
     * dan menenggelamkan keluhan desa terpencil yang pendukungnya sedikit.
     * Tidak menyediakannya membuat keputusan itu tidak dapat dibatalkan
     * diam-diam dari sisi aplikasi.
     *
     * @param  array{sort: string, lat?: ?float, lng?: ?float, radius?: ?int, district?: ?string,
     *               status?: ?string, category?: ?string}  $opts  sudah divalidasi pemanggil
     */
    public function feed(array $opts): LengthAwarePaginator
    {
        $query = $this->applyFilters($this->visible(), $opts)
            ->with([
                'category',
                'opd',
                // Foto warga dulu, lalu bukti OPD: kiriman adalah slider, dan
                // bukti ditampilkan aplikasi sebagai "Sesudah".
                'attachments' => fn ($q) => $q
                    ->orderByRaw('kind = ?', [Attachment::KIND_EVIDENCE])
                    ->orderBy('created_at'),
            ]);

        $hasPosition = isset($opts['lat'], $opts['lng']);

        if ($hasPosition) {
            $this->withDistance($query, (float) $opts['lat'], (float) $opts['lng'], $opts['radius'] ?? null);
        } elseif (! empty($opts['district'])) {
            $query->where('district_code', $opts['district']);
        }

        match ($opts['sort']) {
            'nearest' => $query
                ->orderByRaw('distance_meters IS NULL')
                ->orderByRaw('distance_meters ASC')
                ->latest('received_at'),

            // Waktu SELESAI, bukan waktu masuk: laporan yang masuk tiga bulan
            // lalu dan selesai kemarin justru yang paling layak disebut "baru
            // selesai". `resolved_at` di jawaban berasal dari kolom ini.
            'recently_resolved' => $query->orderByDesc('verified_at'),

            default => $query->latest('received_at'),
        };

        return $query->paginate(20);
    }

    /**
     * Tambahkan kolom `distance_meters` dan, bila diminta, saring radius.
     *
     * Haversine, dihitung di basis data supaya pengurutan dan penyaringan
     * radius terjadi SEBELUM paginasi. Menghitungnya di PHP berarti menarik
     * seluruh laporan lebih dulu, lalu mengurutkan sebagian kecil saja, dan
     * halaman kedua akan berisi data yang salah.
     *
     * Satu tempat untuk peta dan linimasa, supaya keduanya menghitung jarak
     * dengan cara yang sama persis.
     */
    protected function withDistance(Builder $query, float $latitude, float $longitude, ?int $radiusMeters): void
    {
        $haversine = '(6371000 * ACOS(LEAST(1.0, GREATEST(-1.0,'
            .' COS(RADIANS(?)) * COS(RADIANS(latitude))'
            .' * COS(RADIANS(longitude) - RADIANS(?))'
            .' + SIN(RADIANS(?)) * SIN(RADIANS(latitude))'
            .'))))';

        $query->selectRaw("*, CASE WHEN latitude IS NULL OR longitude IS NULL THEN NULL ELSE {$haversine} END AS distance_meters",
            [$latitude, $longitude, $latitude]);

        if ($radiusMeters !== null) {
            // Laporan tanpa koordinat tetap lolos: radius menyaring yang
            // diketahui jauh, bukan yang tidak diketahui letaknya.
            $query->where(function ($q) use ($haversine, $latitude, $longitude, $radiusMeters) {
                $q->whereNull('latitude')
                    ->orWhereNull('longitude')
                    ->orWhereRaw("{$haversine} <= ?", [$latitude, $longitude, $latitude, $radiusMeters]);
            });
        }
    }

    /**
     * Bulatkan jarak sebelum dikirim — PENGAMAN, bukan kerapian.
     *
     * Jarak setepat meter dari beberapa posisi berbeda cukup untuk menghitung
     * balik koordinat laporan. Pembulatan membuat jawabannya sengaja kabur:
     * yang tersisa adalah lingkaran, bukan titik.
     *
     * Di bawah 100 m dibulatkan ke 50 m — justru jarak dekat yang paling
     * berbahaya, sebab di situ satu lingkaran kecil sudah hampir menunjuk satu
     * rumah. Selebihnya ke puluhan meter.
     */
    public static function roundDistance(?float $meters): ?int
    {
        if ($meters === null) {
            return null;
        }

        if ($meters < 100) {
            return (int) (round($meters / 50) * 50);
        }

        return (int) (round($meters / 10) * 10);
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        // Kategori disaring lewat KODE, bukan id: kode itulah yang dipegang
        // aplikasi dan dapat dibaca manusia di URL.
        //
        // Boleh beberapa sekaligus, dipisah koma (`jalan,lampu`): lembar saring
        // linimasa memilih lebih dari satu. Satu kode tanpa koma tetap bekerja
        // seperti sebelumnya, jadi peta tidak berubah.
        if (! empty($filters['category'])) {
            $codes = array_values(array_filter(array_map('trim', explode(',', (string) $filters['category']))));
            $query->whereHas('category', fn ($q) => $q->whereIn('code', $codes));
        }

        // "diproses" menggabungkan beberapa status menjadi satu pilihan yang
        // masuk akal bagi warga — mereka tidak membedakan `dispatched` dari
        // `in_progress`, dan tidak perlu.
        if (! empty($filters['status'])) {
            match ($filters['status']) {
                'in_progress' => $query->whereIn('status', [
                    Report::STATUS_DISPATCHED,
                    Report::STATUS_IN_PROGRESS,
                    Report::STATUS_AWAITING_VERIFICATION,
                ]),
                'resolved' => $query->where('status', Report::STATUS_RESOLVED),
                default => null,
            };
        }

        return $query;
    }

    /**
     * Status terbanyak untuk tiap kecamatan.
     *
     * @param  array<int,string>  $codes
     * @return array<string,string>
     */
    protected function dominantStatuses(array $codes, array $filters): array
    {
        $rows = $this->applyFilters($this->visible(), $filters)
            ->whereIn('district_code', $codes)
            ->selectRaw('district_code, status, COUNT(*) as jumlah')
            ->groupBy('district_code', 'status')
            ->orderByDesc('jumlah')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            // Sudah terurut menurun, jadi yang pertama tiap kecamatan adalah
            // yang terbanyak.
            $out[$row->district_code] ??= $row->status;
        }

        return $out;
    }
}
