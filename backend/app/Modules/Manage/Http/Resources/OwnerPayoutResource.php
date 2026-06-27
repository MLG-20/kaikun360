<?php

namespace App\Modules\Manage\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un reversement au propriétaire (module Manage).
 *
 * @mixin \App\Modules\Manage\Models\OwnerPayout
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
        ];
    }
}
