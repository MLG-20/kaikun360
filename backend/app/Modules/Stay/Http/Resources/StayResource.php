<?php

namespace App\Modules\Stay\Http\Resources;

use App\Modules\Immo\Http\Resources\PropertyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'une nuitée (configuration Stay + bien associé).
 *
 * @mixin \App\Modules\Stay\Models\Stay
 */
class StayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'price_per_night_xof' => $this->price_per_night_xof,
            'caution_xof' => $this->caution_xof,
            'capacity' => $this->capacity,
            'min_nights' => $this->min_nights,
            'max_nights' => $this->max_nights,
            'rules' => $this->rules,
            'amenities' => $this->amenities,
            'check_in_time' => $this->check_in_time,
            'check_out_time' => $this->check_out_time,
            // Le bien sous-jacent (réutilise la Resource du module Immo).
            'property' => PropertyResource::make($this->whenLoaded('property')),
            'created_at' => $this->created_at,
        ];
    }
}
