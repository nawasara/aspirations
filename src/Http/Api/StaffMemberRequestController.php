<?php

namespace Nawasara\Aspirations\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nawasara\Registry\Exceptions\MembershipRequestException;
use Nawasara\Registry\Models\MembershipRequest;
use Nawasara\Registry\Models\Opd;
use Nawasara\Registry\Services\MembershipRequestService;
use Nawasara\Registry\Support\MembershipResolver;

/**
 * Admin OPD mengajukan petugas baru dari panel GO (15 Nov 2026).
 *
 * Mengajukan, BUKAN menautkan. Keputusan tetap di admin Registry Nawasara
 * (Registry Aset → Pengajuan Petugas), karena menautkan pegawai ke OPD berarti
 * memberinya akses ke seluruh laporan OPD itu — lihat MembershipRequestService.
 *
 * Dibatasi ke OPD si admin oleh MembershipResolver, dengan sifat fail-closed
 * yang sama seperti StaffOpdMemberController: tanpa keanggotaan, tidak bisa
 * mengajukan dan tidak melihat apa pun.
 */
class StaffMemberRequestController
{
    public function __construct(
        protected MembershipResolver $memberships,
        protected MembershipRequestService $service,
    ) {}

    /** GET /api/v1/staff/aspirations/opd/member-requests */
    public function index(Request $request): JsonResponse
    {
        $opdId = $this->memberships->opdIdFor($request->user());

        if ($opdId === null) {
            return response()->json(['data' => [], 'meta' => ['reason' => 'not_linked_to_opd']]);
        }

        $labels = MembershipRequestService::allowedRoles();

        $rows = MembershipRequest::query()
            ->where('opd_id', $opdId)
            ->latest('id')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (MembershipRequest $r) => $this->present($r, $labels))->all(),
            'meta' => ['roles' => $labels],
        ]);
    }

    /** POST /api/v1/staff/aspirations/opd/member-requests */
    public function store(Request $request): JsonResponse
    {
        $opdId = $this->memberships->opdIdFor($request->user());

        if ($opdId === null) {
            return response()->json([
                'error' => ['code' => 'not_linked_to_opd', 'message' => 'Akun Anda belum tertaut ke OPD mana pun.'],
            ], 403);
        }

        $data = $request->validate([
            'nip' => ['required', 'string', 'max:32'],
            'role' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $created = $this->service->submit(
                Opd::findOrFail($opdId),
                $data['nip'],
                $data['role'],
                $data['note'] ?? null,
                $request->user(),
            );
        } catch (MembershipRequestException $e) {
            return response()->json(['error' => ['code' => 'request_rejected', 'message' => $e->getMessage()]], 422);
        }

        return response()->json(['data' => $this->present($created, MembershipRequestService::allowedRoles())], 201);
    }

    /** @param  array<string,string>  $labels */
    protected function present(MembershipRequest $r, array $labels): array
    {
        return [
            'id' => $r->id,
            'nip' => $r->nip,
            'name' => $r->name,
            'role' => $r->role,
            'role_label' => $labels[$r->role] ?? $r->role,
            'note' => $r->note,
            'status' => $r->status,
            'decision_note' => $r->decision_note,
            'created_at' => $r->created_at?->toIso8601String(),
            'decided_at' => $r->decided_at?->toIso8601String(),
        ];
    }
}
