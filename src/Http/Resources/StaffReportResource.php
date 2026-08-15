<?php

namespace Nawasara\Aspirations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bentuk laporan untuk PANEL STAF.
 *
 * Berbeda dari ReportResource: staf perlu melihat keadaan SLA, siapa
 * mengerjakan, dan penanda foto bukti. Yang TIDAK berbeda — dan tidak boleh
 * berbeda — adalah penyaringan identitas anonim (#8).
 *
 * ⚠️ Justru di sinilah aturan #8 paling penting. Panel OPD adalah tempat yang
 * paling mungkin membocorkan nama pelapor pungli, dan `keycloak_sub` tidak
 * pernah keluar dari sini dalam keadaan apa pun. Membukanya adalah wewenang
 * Inspektorat lewat endpoint tersendiri yang tercatat (#10), bukan efek
 * samping dari membuka daftar laporan.
 */
class StaffReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'status' => $this->status,
            'title' => $this->title,
            'description' => $this->description,

            'category' => [
                'code' => $this->whenLoaded('category', fn () => $this->category->code),
                'name' => $this->whenLoaded('category', fn () => $this->category->name),
                'is_sensitive' => $this->whenLoaded('category', fn () => (bool) $this->category->is_sensitive),
            ],

            'opd_name' => $this->whenLoaded('opd', fn () => $this->opd?->name),

            'location' => [
                'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
                'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
                'address' => $this->full_address,
                // Akurasi ditampilkan supaya petugas tahu seberapa jauh titiknya
                // dapat dipercaya sebelum berangkat ke lapangan.
                'accuracy_m' => $this->location_accuracy !== null ? (float) $this->location_accuracy : null,
            ],

            'is_anonymous' => (bool) $this->is_anonymous,

            // Sama seperti sisi warga: nama disaring, `keycloak_sub` tidak
            // pernah ikut.
            'reporter_name' => $this->is_anonymous ? 'Warga Ponorogo' : ($this->citizen_name ?? 'Warga Ponorogo'),

            // ── SLA: target, aktual, dan keadaan sekarang ──
            'sla' => [
                'received_at' => $this->received_at?->toIso8601String(),
                'response_due_at' => $this->response_due_at?->toIso8601String(),
                'first_responded_at' => $this->first_responded_at?->toIso8601String(),
                'due_at' => $this->sla_due_at?->toIso8601String(),
                'resolution_submitted_at' => $this->resolution_submitted_at?->toIso8601String(),
                'verification_due_at' => $this->verification_due_at?->toIso8601String(),

                // Dihitung server, bukan di panel. Dua panel yang menghitung
                // sendiri akan berbeda hasilnya begitu salah satu lupa
                // diperbarui.
                'response_overdue' => $this->isResponseOverdue(),
                'resolution_overdue' => $this->isResolutionOverdue(),
                'verification_overdue' => $this->isVerificationOverdue(),
                'response_hours' => $this->responseHours(),
                'solution_hours' => $this->solutionHours(),
            ],

            'responded_by' => $this->whenLoaded('responder', fn () => $this->responder?->name),
            'verifier_name' => $this->whenLoaded('verifier', fn () => $this->verifier?->name),
            'verified_at' => $this->verified_at?->toIso8601String(),

            'rating' => $this->rating,
            'support_count' => (int) $this->support_count,

            'photos' => StaffAttachmentResource::collection($this->whenLoaded('attachments')),
            'timeline' => ResponseResource::collection($this->whenLoaded('responses')),
        ];
    }
}
