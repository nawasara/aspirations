<?php

namespace Nawasara\Aspirations\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Nawasara\Aspirations\Models\Report;

/**
 * Ringkasan dashboard — dihitung di basis data, bukan dengan menyedot baris.
 *
 * ## Kenapa ini ada
 *
 * Panel OPD sebelumnya menghitung ringkasannya sendiri dengan menarik SELURUH
 * laporan: sampai 30 permintaan beruntun, 3.000 baris lengkap beserta
 * `timeline`, `photos`, dan `location` yang tak satu pun dipakai untuk
 * menggambar angka.
 *
 * Yang lebih berbahaya daripada lambatnya: `MAX_PAGES = 30` membuatnya **salah
 * diam-diam**. Di atas 3.000 laporan ia berhenti menghitung tanpa memberi tahu
 * siapa pun, dan angkanya tetap tampil meyakinkan. Kegagalan yang menampilkan
 * angka salah lebih berbahaya daripada yang menampilkan galat.
 *
 * ## Isolasi per-OPD
 *
 * Tidak ada satu pun where-clause OPD di kelas ini — global scope pada model
 * yang mengerjakannya. Menyalinnya ke sini berarti dua tempat yang harus
 * sepakat, dan yang satu akan tertinggal.
 */
class DashboardSummary
{
    /**
     * @param  string  $period  `YYYY-MM`, atau `all` untuk semua waktu
     * @return array<string, mixed>
     */
    public function build(string $period = 'all'): array
    {
        [$since, $until] = $this->range($period);
        [$prevSince, $prevUntil] = $this->previousRange($period);

        $total = $this->scoped($since, $until)->count();
        $prevTotal = $prevSince ? $this->scoped($prevSince, $prevUntil)->count() : null;

        return [
            'total' => $total,
            'total_change_pct' => $this->changePct($total, $prevTotal),
            'in_progress' => $this->scoped($since, $until)
                ->whereIn('status', Report::OPEN_STATUSES)->count(),

            // Memakai scopeOverdue(), BUKAN salinan aturannya. Dua salinan
            // aturan SLA akan berbeda begitu salah satu diperbarui, dan yang
            // berbeda adalah angka kepatuhan yang dilaporkan ke pimpinan.
            'overdue' => $this->scoped($since, $until)->overdue()->count(),
            'resolved' => $this->scoped($since, $until)
                ->where('status', Report::STATUS_RESOLVED)->count(),
            'average_rating' => $this->averageRating($since, $until),
            'daily_trend' => $this->dailyTrend($since, $until),
            'top_categories' => $this->topCategories($since, $until),
            'map_points' => $this->mapPoints($since, $until),
            'attention' => $this->attention(),
        ];
    }

    /**
     * Dasar SEMUA hitungan. Global scope OPD ikut terbawa dari model.
     */
    protected function scoped(?Carbon $since, ?Carbon $until)
    {
        return Report::query()
            ->when($since, fn ($q) => $q->where('received_at', '>=', $since))
            ->when($until, fn ($q) => $q->where('received_at', '<', $until));
    }

    /**
     * ⚠️ `null`, bukan `0`, bila tidak ada pembandingnya.
     *
     * Nol berarti "tidak berubah". Tidak adanya periode sebelumnya berbeda
     * artinya, dan menampilkannya sebagai 0% membuat bulan pertama sebuah OPD
     * terlihat stagnan padahal ia baru mulai.
     */
    protected function changePct(int $now, ?int $prev): ?float
    {
        if ($prev === null || $prev === 0) {
            return null;
        }

        return round((($now - $prev) / $prev) * 100, 1);
    }

    /**
     * Rata-rata penilaian warga — `null` bila belum ada yang menilai.
     */
    protected function averageRating(?Carbon $since, ?Carbon $until): ?float
    {
        $avg = $this->scoped($since, $until)->whereNotNull('rating')->avg('rating');

        return $avg === null ? null : round((float) $avg, 1);
    }

    /**
     * Masuk vs selesai per hari.
     *
     * Dua kueri teragregat, bukan satu per hari: rentang sebulan berarti 60
     * kueri bila dipecah, dan itu sekadar memindahkan masalah dari panel ke
     * server.
     *
     * @return array<int, array{date:string, incoming:int, resolved:int}>
     */
    protected function dailyTrend(?Carbon $since, ?Carbon $until): array
    {
        $masuk = $this->scoped($since, $until)
            ->selectRaw('DATE(received_at) d, COUNT(*) n')
            ->groupBy('d')->pluck('n', 'd');

        $selesai = Report::query()
            ->where('status', Report::STATUS_RESOLVED)
            ->when($since, fn ($q) => $q->where('verified_at', '>=', $since))
            ->when($until, fn ($q) => $q->where('verified_at', '<', $until))
            ->selectRaw('DATE(verified_at) d, COUNT(*) n')
            ->groupBy('d')->pluck('n', 'd');

        $hari = $masuk->keys()->merge($selesai->keys())->unique()->sort()->values();

        return $hari->map(fn ($d) => [
            'date' => (string) $d,
            'incoming' => (int) ($masuk[$d] ?? 0),
            'resolved' => (int) ($selesai[$d] ?? 0),
        ])->all();
    }

    /**
     * @return array<int, array{category:string, count:int}>
     */
    protected function topCategories(?Carbon $since, ?Carbon $until): array
    {
        return $this->scoped($since, $until)
            ->join('nawasara_aspirations_categories as c', 'c.id', '=', 'nawasara_aspirations_reports.category_id')
            ->selectRaw('c.name nama, COUNT(*) n')
            ->groupBy('c.name')
            ->orderByDesc('n')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['category' => $r->nama, 'count' => (int) $r->n])
            ->all();
    }

    /**
     * Sebaran per kecamatan.
     *
     * Nama kecamatan diambil dari master wilayah, bukan kolom teks: kolom itu
     * kosong pada seluruh laporan produksi karena geocoder bawaannya
     * NullGeocoder, sedangkan `district_code` selalu terisi dari koordinat.
     *
     * @return array<int, array{district_code:string, district_name:?string, total:int}>
     */
    protected function mapPoints(?Carbon $since, ?Carbon $until): array
    {
        return $this->scoped($since, $until)
            ->whereNotNull('district_code')
            ->selectRaw('district_code, COUNT(*) n')
            ->groupBy('district_code')
            ->orderByDesc('n')
            ->get()
            ->map(function ($r) {
                $nama = DB::table('nawasara_aspirations_districts')
                    ->where('code', $r->district_code)->value('name');

                return [
                    'district_code' => $r->district_code,
                    'district_name' => $nama,
                    'total' => (int) $r->n,
                ];
            })->all();
    }

    /**
     * Yang paling menuntut perhatian SEKARANG.
     *
     * Sengaja TIDAK disaring periode: laporan telat dari bulan lalu tetap telat
     * hari ini, dan menyembunyikannya karena periodenya berganti adalah cara
     * paling halus untuk melupakannya.
     *
     * @return array<int, array{code:string, title:?string, overdue_days:int}>
     */
    protected function attention(): array
    {
        return Report::query()
            ->overdue()
            ->orderBy('sla_due_at')
            ->limit(5)
            ->get(['code', 'title', 'sla_due_at', 'response_due_at'])
            ->map(function (Report $r) {
                $batas = $r->sla_due_at ?? $r->response_due_at;

                return [
                    'code' => $r->code,
                    'title' => $r->title,
                    'overdue_days' => $batas ? (int) $batas->diffInDays(now()) : 0,
                ];
            })->all();
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    protected function range(string $period): array
    {
        if ($period === 'all') {
            return [null, null];
        }

        $awal = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfDay();

        return [$awal, $awal->copy()->addMonth()];
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    protected function previousRange(string $period): array
    {
        if ($period === 'all') {
            return [null, null];
        }

        [$awal] = $this->range($period);

        return [$awal->copy()->subMonth(), $awal];
    }
}
