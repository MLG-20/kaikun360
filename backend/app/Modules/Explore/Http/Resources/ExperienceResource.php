<?php

namespace App\Modules\Explore\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'une expérience touristique (module Explore).
 *
 * `seats_left` (places restantes) n'est exposé que lorsqu'il a été calculé par
 * le contrôleur et placé dans l'attribut `seats_left` du modèle.
 *
 * @mixin \App\Modules\Explore\Models\TourismExperience
 */
class ExperienceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'title' => $this->title,
            'destination' => $this->destination,
            'description' => $this->description,
            'duration_days' => $this->duration_days,
            'price_xof' => $this->price_xof,
            'capacity' => $this->capacity,
            'inclusions' => $this->inclusions ?? [],
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'published_at' => $this->published_at?->toIso8601String(),
            'seats_left' => $this->when(isset($this->seats_left), fn () => (int) $this->seats_left),
        ];
    }
}
