<?php

namespace Nawasara\Aspirations\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Nawasara\Registry\Support\MembershipResolver;

/**
 * Admin OPD menetapkan verifikatornya sendiri.
 *
 * ## Kenapa ini ada
 *
 * Menjadikan seseorang verifikator sebelumnya menuntut DUA langkah yang
 * keduanya hanya dapat dilakukan tim Nawasara: memberi peran (layar pengguna
 * `nawasara-core`) dan menautkan ke OPD (layar Registry).
 *
 * Ponorogo punya 21 kecamatan dan puluhan OPD. Menjadikan setiap mutasi,
 * pensiun, dan rotasi jabatan sebagai tiket ke tim pusat berarti laporan
 * menggantung sementara SLA terus berjalan — dan warga yang menagih tidak tahu
 * sebabnya ada di birokrasi internal.
 *
 * ## Kenapa izin yang sudah ada tidak cukup
 *
 * Memberi admin OPD akses ke layar pengguna `nawasara-core` BUKAN jawabannya:
 * layar itu tidak disaring per OPD. Siapa pun yang membukanya dapat mengubah
 * peran seluruh pegawai se-kabupaten. Admin Dinas PU tidak boleh dapat
 * mengubah peran pegawai Dinas Kesehatan.
 *
 * ## Tiga hal yang membuatnya aman dilimpahkan
 *
 * 1. **Dibatasi ke OPD pemegang izin.** `MembershipResolver` yang menentukan,
 *    dan sifat fail-closed-nya terbawa: admin tanpa keanggotaan melihat NOL
 *    pegawai, bukan semuanya.
 * 2. **Hanya peran verifikator** yang dapat diberikan dan dicabut. Admin OPD
 *    tidak boleh dapat menjadikan seseorang developer.
 * 3. **Tidak menyentuh keanggotaan OPD.** Penautan tetap milik admin Registry,
 *    karena di situlah tebakan yang meleset paling berbahaya.
 */
class StaffOpdMemberController
{
    /**
     * Satu-satunya peran yang boleh disentuh endpoint ini.
     *
     * Ditulis sebagai konstanta, bukan parameter: begitu peran menjadi masukan
     * dari pemanggil, endpoint ini berubah menjadi alat pemberian peran
     * apa pun — dan admin OPD dapat menjadikan dirinya developer.
     */
    public const VERIFIER_ROLE = 'lapor-opd-kabid';

    public function __construct(
        protected MembershipResolver $memberships,
    ) {}

    /**
     * GET /api/v1/staff/aspirations/opd/members
     */
    public function index(Request $request): JsonResponse
    {
        $opdId = $this->memberships->opdIdFor($request->user());

        // Fail-closed: tanpa keanggotaan, tidak melihat siapa pun. Mengandalkan
        // "ingat untuk menautkannya" adalah kebocoran data yang tinggal
        // menunggu waktu.
        if ($opdId === null) {
            return response()->json([
                'data' => [],
                'meta' => ['reason' => 'not_linked_to_opd'],
            ]);
        }

        $ids = $this->activeMemberIds($opdId);

        if ($ids->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $model = config('auth.providers.users.model');
        $users = $model::query()->whereIn('id', $ids)->orderBy('name')->get();

        return response()->json([
            'data' => $users->map(fn ($u) => [
                'id' => $u->getKey(),
                'name' => $u->name,
                'email' => $u->email,
                'is_verifier' => $u->hasRole(self::VERIFIER_ROLE),
            ])->all(),
        ]);
    }

    /**
     * POST /api/v1/staff/aspirations/opd/members/{id}/verifier
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        // Hanya anggota AKTIF yang dapat dijadikan verifikator (audit keamanan
        // 15 Oktober 2026, temuan R6). Anggota nonaktif sudah tidak punya
        // cakupan OPD di MembershipResolver, jadi peran itu hanya akan
        // menjadi sisa yang aktif kembali tanpa disadari bila keanggotaannya
        // dihidupkan.
        $target = $this->memberOfSameOpd($request, $id, activeOnly: true);

        if ($target instanceof JsonResponse) {
            return $target;
        }

        $target->assignRole(self::VERIFIER_ROLE);

        return response()->json([
            'data' => ['id' => $target->getKey(), 'is_verifier' => true],
        ]);
    }

    /**
     * DELETE /api/v1/staff/aspirations/opd/members/{id}/verifier
     */
    public function revoke(Request $request, int $id): JsonResponse
    {
        $target = $this->memberOfSameOpd($request, $id);

        if ($target instanceof JsonResponse) {
            return $target;
        }

        // ⚠️ Jangan biarkan OPD kehilangan verifikator TERAKHIRNYA.
        //
        // Tanpa penjagaan ini, admin dapat mencabut peran dirinya sendiri —
        // dan OPD itu langsung kehilangan seluruh kemampuan verifikasi, yang
        // memulihkannya justru menuntut tim Nawasara lagi. Persis yang hendak
        // dihindari endpoint ini.
        if ($this->isLastVerifier($request, $target)) {
            return response()->json([
                'message' => 'Tidak dapat mencabut satu-satunya pemeriksa di perangkat daerah ini. '
                    .'Tetapkan pemeriksa lain terlebih dahulu.',
            ], 422);
        }

        $target->removeRole(self::VERIFIER_ROLE);

        return response()->json([
            'data' => ['id' => $target->getKey(), 'is_verifier' => false],
        ]);
    }

    /**
     * Pastikan sasaran benar-benar se-OPD dengan pemanggil.
     *
     * @return mixed Model pengguna, atau JsonResponse bila ditolak
     */
    protected function memberOfSameOpd(Request $request, int $id, bool $activeOnly = false): mixed
    {
        $opdId = $this->memberships->opdIdFor($request->user());

        if ($opdId === null) {
            return response()->json([
                'message' => 'Anda belum tertaut ke perangkat daerah mana pun.',
            ], 403);
        }

        // Pencabutan (`$activeOnly` false) tetap menerima anggota nonaktif:
        // membersihkan peran verifikator dari pegawai yang sudah dinonaktifkan
        // justru yang diinginkan.
        $seOpd = DB::table('nawasara_registry_memberships')
            ->where('opd_id', $opdId)
            ->where('user_id', $id)
            ->when($activeOnly, fn ($q) => $q->where('aktif', true))
            ->exists();

        if (! $seOpd) {
            // 404, bukan 403: memberitahu bahwa seseorang ADA tetapi di OPD
            // lain sudah membocorkan keberadaannya.
            return response()->json([
                'message' => 'Pegawai tidak ditemukan di perangkat daerah Anda.',
            ], 404);
        }

        $model = config('auth.providers.users.model');

        return $model::findOrFail($id);
    }

    /**
     * Pengguna dengan keanggotaan AKTIF di OPD ini.
     *
     * Audit keamanan 15 Oktober 2026, temuan R6: daftar ini dulu memuat juga
     * keanggotaan yang sudah dinonaktifkan, sehingga pegawai yang dimutasi
     * atau pensiun masih dapat dijadikan verifikator. MembershipResolver
     * sendiri hanya membaca keanggotaan aktif; daftar di sini disamakan.
     */
    protected function activeMemberIds(int $opdId): \Illuminate\Support\Collection
    {
        return DB::table('nawasara_registry_memberships')
            ->where('opd_id', $opdId)
            ->where('aktif', true)
            ->distinct()
            ->pluck('user_id');
    }

    protected function isLastVerifier(Request $request, mixed $target): bool
    {
        if (! $target->hasRole(self::VERIFIER_ROLE)) {
            return false;
        }

        $opdId = $this->memberships->opdIdFor($request->user());

        // Hanya anggota aktif yang dihitung: verifikator nonaktif tidak dapat
        // memverifikasi apa pun, jadi menghitungnya membiarkan OPD kehilangan
        // verifikator terakhirnya yang sungguh bekerja.
        $ids = $this->activeMemberIds($opdId);

        $model = config('auth.providers.users.model');

        $jumlah = $model::query()
            ->whereIn('id', $ids)
            ->get()
            ->filter(fn ($u) => $u->hasRole(self::VERIFIER_ROLE))
            ->count();

        return $jumlah <= 1;
    }
}
