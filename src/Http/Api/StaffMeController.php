<?php

namespace Nawasara\Aspirations\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nawasara\Aspirations\Models\Report;
use Nawasara\Keycloak\Models\KeycloakUser;
use Nawasara\Registry\Models\Opd;
use Nawasara\Registry\Models\SimasProfile;
use Nawasara\Registry\Services\SsoLoginImporter;
use Nawasara\Registry\Support\MembershipResolver;

/**
 * GET /api/v1/staff/aspirations/me: siapa yang sedang masuk ke panel GO.
 *
 * Tiga sumber, sengaja dipisah:
 *
 *   - **Identitas** dari token (realm pegawai).
 *   - **Peran, izin, OPD** dari Nawasara. Ini satu-satunya yang menentukan
 *     apa yang boleh dilakukan; panel menampilkan menu dari `permissions`,
 *     bukan dari peran di token Keycloak, supaya panel dan server tidak
 *     pernah berbeda pendapat.
 *   - **Jabatan** dari direktori SIMAS (SimasDirectory). Informasi saja,
 *     TIDAK pernah menjadi syarat akses: datanya belum lengkap untuk semua
 *     ASN, dan syarat yang bergantung padanya akan menahan laporan.
 *
 * Di belakang `api.staff.identity`, BUKAN `api.staff`: ASN yang login ke panel
 * tetapi belum diajukan sebagai petugas belum punya baris `users`. `api.staff`
 * menolaknya dengan 403 yang tidak menjelaskan apa-apa; di sini ia mendapat
 * 200 dengan `is_petugas: false`, sehingga panel dapat berkata "minta admin
 * OPD mengajukan Anda".
 *
 * Seluruh isi milik pemanggil sendiri: tidak ada parameter, jadi tidak ada
 * pegawai lain yang dapat dibaca lewat sini.
 */
class StaffMeController
{
    /** Izin yang relevan bagi panel GO. Izin paket lain tidak ikut keluar. */
    private const PERMISSION_PREFIX = 'aspirations.';

    public function __construct(
        protected MembershipResolver $memberships,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $claims = (array) $request->attributes->get('staff_claims', []);
        $sub = (string) ($claims['sub'] ?? '');

        $userModel = config('auth.providers.users.model', \App\Models\User::class);
        $user = $sub !== '' ? $userModel::query()->where('keycloak_id', $sub)->first() : null;

        $account = null;
        if ($user) {
            $resolution = $this->memberships->resolve($user, Report::privilegedRoles());
            $opd = $resolution['opdId'] ? Opd::find($resolution['opdId']) : null;

            $account = [
                'roles' => $user->getRoleNames()->all(),
                'permissions' => $user->getAllPermissions()->pluck('name')->all(),
                'opd' => $opd ? ['id' => $opd->id, 'name' => $opd->name] : null,
                'scope' => $resolution['state'],
                'is_verifier' => $user->hasRole(StaffOpdMemberController::VERIFIER_ROLE),
            ];
        }

        $nip = static::nipFrom($claims);

        return response()->json([
            'data' => static::present($claims, $nip, $account, $nip ? $this->simasFor($nip, $sub) : null),
        ]);
    }

    /**
     * Bentuk jawaban, sebagai DAFTAR-IZIN (AGENTS.md §10a): hanya kolom yang
     * disebut di sini yang keluar. Kolom baru di tabel mana pun tidak ikut
     * terkirim dengan sendirinya.
     *
     * @param  array<string, mixed>  $claims
     * @param  array{roles: array<int,string>, permissions: array<int,string>, opd: ?array, scope: string, is_verifier: bool}|null  $account
     * @param  array<string, mixed>|null  $simas
     * @return array<string, mixed>
     */
    public static function present(array $claims, ?string $nip, ?array $account, ?array $simas): array
    {
        $permissions = array_values(array_filter(
            $account['permissions'] ?? [],
            fn ($p) => is_string($p) && str_starts_with($p, self::PERMISSION_PREFIX),
        ));
        sort($permissions);

        $roles = array_values($account['roles'] ?? []);
        sort($roles);

        return [
            'sub' => $claims['sub'] ?? null,
            'nip' => $nip,
            'name' => $claims['name'] ?? $simas['nama'] ?? $claims['preferred_username'] ?? null,
            'email' => $claims['email'] ?? null,

            // Punya akun Nawasara DAN boleh membuka laporan. Akun tanpa izin
            // apa pun (mis. peran dicabut) bukan petugas, meski barisnya ada.
            'is_petugas' => in_array('aspirations.report.view', $permissions, true),
            'roles' => $roles,
            'permissions' => $permissions,
            'opd' => $account['opd'] ?? null,
            // all = lintas OPD (Inspektorat, admin kabupaten), opd = satu OPD,
            // none = belum tertaut OPD mana pun (tidak melihat laporan apa pun).
            'scope' => match ($account['scope'] ?? null) {
                'privileged' => 'all',
                'member' => 'opd',
                default => 'none',
            },
            'is_verifier' => (bool) ($account['is_verifier'] ?? false),

            'simas' => $simas === null ? null : [
                'nama' => $simas['nama'] ?? null,
                'jabatan' => $simas['nama_jabatan'] ?? null,
                'jenis_jabatan' => $simas['jenis_jabatan'] ?? null,
                'jenis_kepegawaian' => $simas['jenis_kepegawaian'] ?? null,
                'skpd' => $simas['skpd'] ?? null,
                'unit' => $simas['unit'] ?? null,
                'synced_at' => $simas['synced_at'] ?? null,
            ],
        ];
    }

    /**
     * NIP pemanggil. Username realm pegawai ADALAH NIP; selain 18 digit
     * (akun layanan, akun uji) dianggap tidak punya NIP, bukan ditebak.
     */
    public static function nipFrom(array $claims): ?string
    {
        foreach ([$claims['preferred_username'] ?? null, $claims['nip'] ?? null] as $candidate) {
            if (is_string($candidate) && preg_match('/^\d{18}$/', $candidate) === 1) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Data SIMAS terbaru untuk NIP ini: direktori, atau atribut Keycloak bila
     * lebih baru. Keduanya dibandingkan karena pegawai yang BARU login sudah
     * punya data segar di Keycloak, sementara direktori baru menyalinnya tiap
     * jam. Hanya status `ok` yang ditampilkan.
     *
     * @return array<string, mixed>|null
     */
    protected function simasFor(string $nip, string $sub): ?array
    {
        $profile = SimasProfile::where('nip', $nip)->where('status', SimasProfile::STATUS_OK)->first();

        $fromProfile = $profile ? [
            'nama' => $profile->nama,
            'nama_jabatan' => $profile->nama_jabatan,
            'jenis_jabatan' => $profile->jenis_jabatan,
            'jenis_kepegawaian' => $profile->jenis_kepegawaian,
            'skpd' => $profile->skpd,
            'unit' => $profile->unit,
            'synced_at' => $profile->synced_at?->toIso8601String(),
            '_at' => $profile->synced_at,
        ] : null;

        $kcUser = $sub !== '' ? KeycloakUser::where('user_id', $sub)->first() : null;
        $fromKeycloak = null;

        if ($kcUser && $kcUser->attributeValue('simas_status') === 'ok') {
            $at = SsoLoginImporter::parseTime($kcUser->attributeValue('simas_synced_at'));
            $fromKeycloak = [
                'nama' => $kcUser->attributeValue('simas_nama'),
                'nama_jabatan' => $kcUser->attributeValue('simas_jabatan'),
                'jenis_jabatan' => $kcUser->attributeValue('simas_jenis_jabatan'),
                'jenis_kepegawaian' => $kcUser->attributeValue('simas_jenis_kepegawaian'),
                'skpd' => $kcUser->attributeValue('simas_skpd'),
                'unit' => $kcUser->attributeValue('simas_unit'),
                'synced_at' => $at?->toIso8601String(),
                '_at' => $at,
            ];
        }

        $pick = match (true) {
            $fromProfile === null => $fromKeycloak,
            $fromKeycloak === null => $fromProfile,
            default => ($fromKeycloak['_at'] && (! $fromProfile['_at'] || $fromKeycloak['_at']->gt($fromProfile['_at'])))
                ? $fromKeycloak : $fromProfile,
        };

        if ($pick !== null) {
            unset($pick['_at']);
        }

        return $pick;
    }
}
