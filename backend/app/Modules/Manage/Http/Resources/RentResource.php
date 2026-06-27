<?php

namespace App\Modules\Manage\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'une échéance de loyer (module Manage).
 *
 * @mixin \App\Modules\Manage\Models\Rent
 */
class RentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mandate_id' => $this->mandate_id,
            'tenant_id' => $this->tenant_id,
            'tenant_name' => $this->tenant_name,
            'period_label' => $this->period_label,
            'due_date' => $this->due_date?->toDateString(),
            'amount_xof' => (int) $this->amount_xof,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'paid_at' => $this->paid_at?->toIso8601String(),
        ];
    }
}
