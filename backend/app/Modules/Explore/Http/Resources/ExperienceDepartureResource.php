<?php

namespace App\Modules\Explore\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'une date de départ de circuit (F21).
 *
 * `seats_left` n'est exposé que lorsqu'il a été calculé par le contrôleur et
 * déposé sur l'attribut `seats_left` du modèle (coûterait une requête par
 * date sinon, y compris là où ce n'est pas nécessaire — ex. le formulaire
 * d'édition du prestataire, qui n'a besoin que des places totales).
 *
 * @mixin \App\Modules\Explore\Models\TourismExperienceDeparture
 */
class ExperienceDepartureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'start_date' => $this->start_date?->toDateString(),
            'seats_total' => $this->seats_total,
            'seats_left' => $this->when(isset($this->seats_left), fn () => (int) $this->seats_left),
        ];
    }
}
