<?php

namespace Nawasara\Aspirations\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Nawasara\Registry\Support\MembershipResolver;

/**
 * Siapa saja yang boleh dipilih sebagai pemeriksa.
 *
 * ## Kenapa satu tempat
 *
 * Aturannya ditegakkan `ReportWorkflow::assertVerifierUsable()` saat laporan
 * DISERAHKAN. Tetapi daftar pilihannya digambar di dua tempat — panel Livewire
 * dan panel OPD (Next.js) — dan sebelum kelas ini ada, keduanya menyalin
 * aturannya sendiri-sendiri.
 *
 * Salinan aturan selalu berakhir sama: satu diperbarui, satunya tidak, lalu
 * petugas disodori nama yang pasti ditolak. Ia baru tahu setelah mengunggah
 * foto bukti dan menulis catatan — pekerjaan yang terbuang justru di ujung.
 *
 * Komentar di `assertVerifierUsable` sendiri sudah menyebut niat ini:
 * "supaya panel dapat menyaring daftar pilihan memakai aturan yang sama persis
 * dengan yang ditegakkan saat menyimpan".
 *
 * ## Aturan yang diterapkan
 *
 * | # | Aturan | Sifat |
 * |---|---|---|
 * | 16 | Bukan diri sendiri | mutlak |
 * | 26 | Pengguna aktif | mutlak |
 * | 25 | Se-OPD, bila KEDUANYA punya keanggotaan | melonggar sendiri |
 * | — | Berhak memverifikasi (`aspirations.report.verify`) | mutlak |
 *
 * ⚠️ Aturan #25 sengaja longgar. Saat ini 13 dari 24 pengguna belum tertaut
 * OPD mana pun; aturan yang kaku akan menolak hampir separuh penyerahan pada
 * hari pertama. Ia mengetat sendiri seiring data registry membaik — tanpa
 * mengubah kode di sini.
 */
class VerifierDirectory
{
    public function __construct(
        protected MembershipResolver $memberships,
    ) {}

    /**
     * Calon pemeriksa untuk seorang petugas.
     *
     * @return Collection<int, object{id:int, name:string, email:?string, opd_id:?int, opd_name:?string, membership:string}>
     */
    public function candidatesFor(Authenticatable $actor, ?string $search = null): Collection
    {
        $actorId = (int) $actor->getAuthIdentifier();
        $opdId = $this->memberships->opdIdFor($actor);

        $seOpd = $opdId === null
            ? collect()
            : DB::table('nawasara_registry_memberships')
                ->where('opd_id', $opdId)
                ->where('user_id', '!=', $actorId)
                ->distinct()
                ->pluck('user_id');

        // Pengguna tanpa keanggotaan — sah menurut aturan #25 selama data
        // registry belum lengkap.
        $tanpaOpd = DB::table('users')
            ->whereNotIn('id', DB::table('nawasara_registry_memberships')->pluck('user_id'))
            ->where('id', '!=', $actorId)
            ->pluck('id');

        $ids = $seOpd->merge($tanpaOpd)->unique();

        if ($ids->isEmpty()) {
            return collect();
        }

        $query = DB::table('users')->whereIn('id', $ids);

        // Penyaringan di SERVER, bukan di panel. Sebelumnya panel menarik
        // seluruh direktori pegawai lalu menyaring di memori — dan untuk itu
        // ia butuh token yang boleh membaca semua orang.
        if ($search !== null && trim($search) !== '') {
            $s = trim($search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('username', 'like', "%{$s}%");
            });
        }

        $rows = $query->orderBy('name')->get(['id', 'name', 'email']);

        if ($rows->isEmpty()) {
            return collect();
        }

        return $this->onlyThoseWhoMayVerify($rows)
            ->map(fn (object $u) => $this->withMembership($u))
            ->values();
    }

    /**
     * Apakah petugas ini punya calon pemeriksa sama sekali?
     *
     * Dipisah dari "pencarian tidak menemukan apa-apa" karena keduanya menuntut
     * kalimat yang berbeda di layar: yang satu menyuruh menghubungi admin, yang
     * satu menyuruh mengubah kata kuncinya. Daftar kosong tanpa pembeda membuat
     * petugas menyalahkan pencariannya sendiri.
     */
    public function hasAnyCandidate(Authenticatable $actor): bool
    {
        return $this->candidatesFor($actor)->isNotEmpty();
    }

    /**
     * Saring yang benar-benar berhak memverifikasi.
     *
     * Dikerjakan lewat model pengguna, bukan kueri langsung ke tabel Spatie:
     * izin dapat menempel lewat peran MAUPUN langsung ke pengguna, dan hanya
     * `can()` yang menghitung keduanya.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    protected function onlyThoseWhoMayVerify(Collection $rows): Collection
    {
        $model = config('auth.providers.users.model');

        $users = $model::query()
            ->whereIn('id', $rows->pluck('id'))
            ->get()
            ->keyBy('id');

        return $rows->filter(function (object $u) use ($users) {
            $user = $users->get($u->id);

            return $user !== null && $user->can('aspirations.report.verify');
        });
    }

    /**
     * Lekatkan OPD dan penanda keanggotaannya.
     *
     * `opd_name` bukan hiasan: bila dua pegawai bernama mirip, petugas tidak
     * punya cara membedakannya. `membership` menjelaskan kenapa seseorang
     * muncul padahal beda dinas — atau kenapa rekannya tidak muncul.
     */
    protected function withMembership(object $u): object
    {
        $opd = DB::table('nawasara_registry_memberships as m')
            ->join('nawasara_registry_opd as o', 'o.id', '=', 'm.opd_id')
            ->where('m.user_id', $u->id)
            ->select('o.id', 'o.name')
            ->first();

        $u->opd_id = $opd?->id;
        $u->opd_name = $opd?->name;
        $u->membership = $opd ? 'member' : 'unlinked';

        return $u;
    }
}
