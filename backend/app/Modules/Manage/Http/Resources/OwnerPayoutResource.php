<?php

namespace App\Modules\Manage\Http\Resources;

use App\Modules\Manage\Models\OwnerPayout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * Représentation JSON d'un reversement au propriétaire (module Manage).
 *
 * @mixin OwnerPayout
 */
class OwnerPayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'mandate_id' => $this->mandate_id,
            'owner_id' => $this->owner_id,
            'period_label' => $this->period_label,
            'amount_xof' => (int) $this->amount_xof,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'paid_by' => $this->payer?->name,

            // — Justificatif (2026-08-06). La colonne `proof_path` existait
            // depuis B4.4 sans que rien ne l'écrive : le constat n'exigeait
            // aucune preuve. Elle est désormais obligatoire.
            //
            // ⚠️ Le chemin de stockage n'est JAMAIS exposé — une preuve de
            // virement porte des coordonnées. Seule une URL signée de 10 minutes
            // y donne accès, comme pour le KYC et les certifications.
            'has_proof' => $this->hasProof(),
            'proof_original_name' => $this->proof_original_name,
            'proof_url' => $this->hasProof()
                ? URL::temporarySignedRoute(
                    'manage.payouts.proof',
                    now()->addMinutes(10),
                    ['payout' => $this->id],
                )
                : null,
        ];
    }
}
