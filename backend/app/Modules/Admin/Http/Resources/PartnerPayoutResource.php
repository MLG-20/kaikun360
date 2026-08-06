<?php

namespace App\Modules\Admin\Http\Resources;

use App\Models\PartnerPayout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * Un versement à un partenaire, vu du back-office (F8.16.a).
 *
 * @mixin PartnerPayout
 */
class PartnerPayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,

            'beneficiary' => [
                'id' => $this->beneficiary_id,
                'name' => $this->beneficiary?->name,
                'email' => $this->beneficiary?->email,
                'phone' => $this->beneficiary?->phone,
            ],

            'amount_xof' => $this->amount_xof,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'method' => $this->method,
            'external_reference' => $this->external_reference,
            'paid_at' => $this->paid_at,
            'note' => $this->note,

            // — Justificatif. Le chemin de stockage n'est JAMAIS exposé : une
            // preuve de virement porte des coordonnées bancaires ou un numéro de
            // téléphone. Seule une URL signée de 10 minutes y donne accès, comme
            // pour le KYC et les certifications.
            'has_proof' => $this->proof_path !== null,
            'proof_original_name' => $this->proof_original_name,
            'proof_url' => $this->proof_path === null ? null : URL::temporarySignedRoute(
                'admin.partner-payouts.proof',
                now()->addMinutes(10),
                ['payout' => $this->id],
            ),

            'created_by' => $this->creator?->name,
            'paid_by' => $this->payer?->name,
            'created_at' => $this->created_at,

            // Les dettes soldées, quand elles ont été chargées (fiche).
            'dues' => PartnerDueResource::collection($this->whenLoaded('dues')),
            'dues_count' => $this->whenCounted('dues'),
        ];
    }
}
