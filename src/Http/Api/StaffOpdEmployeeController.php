<?php

namespace Nawasara\Aspirations\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nawasara\Registry\Models\MembershipRequest;
use Nawasara\Registry\Models\Opd;
use Nawasara\Registry\Models\SimasProfile;
use Nawasara\Registry\Services\MembershipRequestService;
use Nawasara\Registry\Support\MembershipResolver;

/**
 * GET /api/v1/staff/aspirations/opd/employees: pegawai OPD admin menurut SIMAS.
 *
 * Supaya admin OPD dapat MEMILIH siapa yang diajukan (terutama Kabid), bukan
 * mengetik NIP dari ingatan. Sumbernya direktori SIMAS registry
 * (SimasDirectory), yang memuat seluruh ASN, bukan hanya pemilik akun.
 *
 * Tiga batas:
 *   - **OPD pemanggil saja**, dari MembershipResolver, fail-closed: tanpa
 *     keanggotaan, daftar kosong. Admin Dinas PU tidak melihat pegawai Dinas
 *     Kesehatan. Digerbang juga izin `aspirations.verifier.manage` di rute.
 *   - **Daftar-izin kolom**: NIP, nama, jabatan, unit. Tidak ada NIK, email,
 *     atau nomor telepon; NIP memang dibutuhkan untuk pengajuan.
 *   - **Jabatan hanya bantuan memilih.** Yang memutuskan tetap admin Registry
 *     saat menyetujui; direktori SIMAS belum lengkap untuk semua ASN, jadi
 *     pegawai yang tidak muncul tetap dapat diajukan manual dengan NIP.
 */
class StaffOpdEmployeeController
{
    private const PER_PAGE = 25;

    public function __construct(
        protected MembershipResolver $memberships,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $opdId = $this->memberships->opdIdFor($request->user());

        if ($opdId === null) {
            return response()->json(['data' => [], 'meta' => ['reason' => 'not_linked_to_opd']]);
        }

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $q = trim((string) ($data['q'] ?? ''));

        $page = SimasProfile::query()
            ->where('simas_opd_id', $opdId)
            ->where('status', SimasProfile::STATUS_OK)
            ->when($q !== '', function ($query) use ($q) {
                // % dan _ dari ketikan dicocokkan apa adanya, bukan sebagai pola.
                $term = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w->where('nama', 'like', $term)
                    ->orWhere('nip', 'like', $term)
                    ->orWhere('nama_jabatan', 'like', $term)
                    ->orWhere('unit', 'like', $term));
            })
            // Jabatan struktural (Kabid, Kasi, Sekretaris) di atas: merekalah
            // yang paling sering dicari saat menunjuk pemeriksa.
            ->orderByRaw("CASE WHEN jenis_jabatan LIKE '%truktural%' THEN 0 ELSE 1 END")
            ->orderBy('nama')
            ->paginate(self::PER_PAGE);

        $nips = collect($page->items())->pluck('nip')->all();
        $officerRoles = $this->officerRolesFor($nips);
        // Tanpa saringan OPD: MembershipRequestService::submit menolak NIP yang
        // masih menunggu di OPD MANA PUN, jadi tombol Ajukan pun harus hilang.
        $pending = MembershipRequest::query()
            ->where('status', MembershipRequest::STATUS_PENDING)
            ->whereIn('nip', $nips)
            ->pluck('role', 'nip')
            ->all();

        $labels = MembershipRequestService::allowedRoles();

        return response()->json([
            'data' => collect($page->items())
                ->map(fn (SimasProfile $p) => static::present($p, $officerRoles[$p->nip] ?? null, $pending[$p->nip] ?? null, $labels))
                ->all(),
            'meta' => [
                'opd' => Opd::whereKey($opdId)->value('name'),
                'roles' => $labels,
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * Satu baris, sebagai DAFTAR-IZIN.
     *
     * @param  array<string,string>  $labels
     * @return array<string, mixed>
     */
    public static function present(SimasProfile $p, ?string $officerRole, ?string $pendingRole, array $labels): array
    {
        return [
            'nip' => $p->nip,
            'name' => $p->nama,
            'jabatan' => $p->nama_jabatan,
            'jenis_jabatan' => $p->jenis_jabatan,
            'is_struktural' => static::isStruktural($p->jenis_jabatan),
            'unit' => $p->unit,
            // Sudah petugas GO? Maka tidak perlu diajukan lagi.
            'officer_role' => $officerRole,
            'officer_role_label' => $officerRole ? ($labels[$officerRole] ?? $officerRole) : null,
            // Sudah diajukan dan menunggu? Supaya tidak diajukan dua kali.
            'pending_role' => $pendingRole,
            'pending_role_label' => $pendingRole ? ($labels[$pendingRole] ?? $pendingRole) : null,
            'synced_at' => $p->synced_at?->toIso8601String(),
        ];
    }

    /** SIMAS menulis "Jabatan Struktural", "STRUKTURAL", dst.; tidak seragam. */
    public static function isStruktural(?string $jenisJabatan): bool
    {
        return $jenisJabatan !== null && stripos($jenisJabatan, 'struktural') !== false;
    }

    /**
     * Peran petugas GO yang sudah dipegang tiap NIP, bila ada.
     *
     * Username akun pegawai adalah NIP (akun dibuat dari direktori Keycloak
     * saat pengajuan disetujui), jadi dicocokkan lewat `username`.
     *
     * @param  array<int,string>  $nips
     * @return array<string,string>  nip → peran
     */
    protected function officerRolesFor(array $nips): array
    {
        if ($nips === []) {
            return [];
        }

        $roleNames = array_keys(MembershipRequestService::allowedRoles());
        $userModel = config('auth.providers.users.model', \App\Models\User::class);

        return $userModel::query()
            ->whereIn('username', $nips)
            ->with('roles:id,name')
            ->get(['id', 'username'])
            ->mapWithKeys(function ($user) use ($roleNames) {
                $held = $user->roles->pluck('name')->intersect($roleNames);
                // Kabid didahulukan bila memegang keduanya.
                $role = $held->contains('lapor-opd-kabid') ? 'lapor-opd-kabid' : $held->first();

                return $role ? [$user->username => $role] : [];
            })
            ->all();
    }
}
