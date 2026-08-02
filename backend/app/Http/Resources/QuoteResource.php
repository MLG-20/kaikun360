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
            // --- Interlocuteur humain (F8.11) --------------------------------
            // Un devis sur-mesure n'est pas un article de catalogue : le client
            // n'achète pas une prestation, il fait confiance à quelqu'un. Le
            // chiffrage arrivait jusqu'ici sans aucun nom — d'où ce bloc, que
            // l'écran affiche en tête du devis.
            // ⚠️ Nullable : les devis antérieurs à F8.11 n'ont pas d'auteur.
            'agent' => $this->whenLoaded('agent', fn () => $this->agent === null ? null : [
                'name' => $this->agent->name,
                'phone' => $this->agent->phone,
                'email' => $this->agent->email,
            ]),
            // --- Suite donnée à l'acceptation (F8.11) ------------------------
            // Accepter ne changeait qu'une colonne : rien ne devenait exigible.
            // La réservation née de l'accord est désormais désignée ici, c'est
            // elle que l'écran de règlement attend.
            'booking_id' => $this->whenLoaded('booking', fn () => $this->booking?->id),
        ];
    }
}
