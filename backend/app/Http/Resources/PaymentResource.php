<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un paiement (B14).
 *
 * @mixin \App\Models\Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'booking_id' => $this->booking_id,
            'provider' => $this->provider,
            'amount_xof' => $this->amount_xof,
            'commission_xof' => $this->commission_xof,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'mode' => $this->mode,
            'created_at' => $this->created_at,
        ];
    }
}
