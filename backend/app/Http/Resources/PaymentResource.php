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
            // F7.3.h — nature du règlement (acompte / solde / intégral).
            'kind' => $this->kind?->value,
            'kind_label' => $this->kind?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'mode' => $this->mode,
            // F7.3.h — état de règlement de la réservation rattachée : c'est là que
            // se lisent les « soldes » du CDC. `whenLoaded` → aucune requête
            // supplémentaire dans les vues qui ne chargent pas la relation.
            'booking' => $this->whenLoaded('booking', fn () => [
                'reference' => $this->booking->reference,
                'amount_xof' => $this->booking->amount_xof,
                'paid_xof' => $this->booking->montantPaye(),
                'remaining_xof' => $this->booking->resteAPayer(),
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
