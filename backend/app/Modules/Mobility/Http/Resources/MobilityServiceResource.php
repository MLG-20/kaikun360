<?php

namespace App\Modules\Mobility\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un service de mobilité (module Mobility).
 *
 * @mixin \App\Modules\Mobility\Models\MobilityService
 */
class MobilityServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'departure' => $this->departure,
            'destination' => $this->destination,
            'departure_at' => $this->departure_at?->toIso8601String(),
            'capacity' => $this->capacity,
            'price_xof' => $this->price_xof,
            'description' => $this->description,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
        ];
    }
}
