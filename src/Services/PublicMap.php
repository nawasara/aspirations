<?php

namespace Nawasara\Aspirations\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as resolved", [Report::STATUS_RESOLVED])
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
     * @param  array<string,mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        // Kategori disaring lewat KODE, bukan id: kode itulah yang dipegang
        // aplikasi dan dapat dibaca manusia di URL.
        if (! empty($filters['category'])) {
            $query->whereHas('category', fn ($q) => $q->where('code', $filters['category']));
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
