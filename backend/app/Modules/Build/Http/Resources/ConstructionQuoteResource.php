<?php

namespace App\Modules\Build\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un devis de chantier (F7.3.e2).
 *
 * Les lignes sont renvoyées telles qu'elles ont été figées à la composition
 * (libellé du lot inclus) : le devis est un document, pas une vue recalculée.
 *
 * @mixin \App\Modules\Build\Models\ConstructionQuote
 */
class ConstructionQuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'construction_request_id' => $this->construction_request_id,
            'lines' => $this->lines,
            'subtotal_xof' => $this->subtotal_xof,
            // Le taux est casté en `decimal:2` (chaîne) : on le rend numérique pour
            // que l'écran l'affiche sans avoir à le convertir.
            'margin_rate' => (float) $this->margin_rate,
            'margin_xof' => $this->margin_xof,
            'total_xof' => $this->total_xof,
            'valid_until' => $this->valid_until?->toDateString(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            // F8.14 — la réservation née de l'acceptation, `null` tant que le
            // devis n'est pas accepté. Sans elle, le montant exigible
            // redeviendrait invisible au rechargement suivant.
            'booking' => $this->whenLoaded(
                'booking',
                fn () => $this->booking ? [
                    'id' => $this->booking->id,
                    'reference' => $this->booking->reference,
                    'status' => $this->booking->status?->value,
                    'is_paid' => $this->booking->estPayee(),
                    'remaining_xof' => $this->booking->resteAPayer(),
                ] : null,
            ),
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author?->id,
                'name' => $this->author?->name,
            ]),
        ];
    }
}
