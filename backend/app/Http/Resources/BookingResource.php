<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'une réservation (transversale).
 *
 * @mixin \App\Models\Booking
 */
class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'guests' => $this->guests,
            'amount_xof' => $this->amount_xof,
            'commission_xof' => $this->commission_xof,
            'caution_xof' => $this->caution_xof,
            'caution_status' => $this->caution_status?->value,
            'cancelled_at' => $this->cancelled_at,
            'created_at' => $this->created_at,
        ];
    }
}
