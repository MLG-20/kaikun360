<?php

namespace App\Modules\TeamBuilding\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un devis team building (module Team Building).
 *
 * @mixin \App\Modules\TeamBuilding\Models\TeamBuildingQuote
 */
class TeamBuildingQuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'request_id' => $this->request_id,
            'lines' => $this->lines ?? [],
            'subtotal_xof' => $this->subtotal_xof,
            'margin_rate' => $this->margin_rate,
            'margin_xof' => $this->margin_xof,
            'total_xof' => $this->total_xof,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
        ];
    }
}
