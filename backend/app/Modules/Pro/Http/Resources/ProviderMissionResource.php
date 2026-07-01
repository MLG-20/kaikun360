<?php

namespace App\Modules\Pro\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'une mission prestataire (module Pro).
 *
 * @mixin \App\Modules\Pro\Models\ProviderMission
 */
class ProviderMissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'provider_id' => $this->provider_id,
            'title' => $this->title,
            'description' => $this->description,
            'amount_xof' => $this->amount_xof,
            'commission_xof' => $this->commission_xof,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
        ];
    }
}
