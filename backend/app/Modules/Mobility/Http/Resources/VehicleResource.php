<?php

namespace App\Modules\Mobility\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un véhicule (module Mobility).
 *
 * @mixin \App\Modules\Mobility\Models\Vehicle
 */
class VehicleResource extends JsonResource
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
            'brand' => $this->brand,
            'model' => $this->model,
            'capacity' => $this->capacity,
            'price_per_day_xof' => $this->price_per_day_xof,
            'has_driver' => $this->has_driver,
            'caution_xof' => $this->caution_xof,
            'description' => $this->description,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
