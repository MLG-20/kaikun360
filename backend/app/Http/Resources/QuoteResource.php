<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un devis générique (couche transversale).
 *
 * @mixin \App\Models\Quote
 */
class QuoteResource extends JsonResource
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
            'amount_xof' => $this->amount_xof,
            'details' => $this->details ?? [],
            'valid_until' => $this->valid_until?->toDateString(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
        ];
    }
}
