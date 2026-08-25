<?php

namespace App\Http\Resources;

use App\Models\PartnerPayout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * Un versement à un partenaire, vu du PARTENAIRE lui-même (« Mes
 * reversements », self-service).
 *
 * ⚠️ N'expose ni `created_by` ni `paid_by` (identité des agents Kaikun) : sans
 * intérêt pour le partenaire. Le justificatif reste accessible par URL signée,
 * mais sur une route DISTINCTE de celle du back-office
 * (`payouts.proof.mine`), qui ne sert QUE des versements déjà scopés au
 * bénéficiaire connecté puisque la signature n'est produite que pour ses
 * propres lignes (cf. `dues()`/`payouts()` du contrôleur).
 *
 * @mixin PartnerPayout
 */
class PartnerPayoutSelfResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,

            'amount_xof' => $this->amount_xof,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'method' => $this->method,
            'paid_at' => $this->paid_at,

            'has_proof' => $this->proof_path !== null,
            'proof_original_name' => $this->proof_original_name,
            'proof_url' => $this->proof_path === null ? null : URL::temporarySignedRoute(
                'payouts.proof.mine',
                now()->addMinutes(10),
                ['payout' => $this->id],
            ),

            'created_at' => $this->created_at,
        ];
    }
}
