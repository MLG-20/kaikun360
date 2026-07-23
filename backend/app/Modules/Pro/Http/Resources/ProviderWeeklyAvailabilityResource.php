<?php

namespace App\Modules\Pro\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un jour du planning hebdomadaire (module Pro, F5.4).
 *
 * @mixin \App\Modules\Pro\Models\ProviderWeeklyAvailability
 */
class ProviderWeeklyAvailabilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'weekday' => $this->weekday,
            'is_open' => (bool) $this->is_open,
            // Heures normalisées en HH:MM (la colonne `time` renvoie HH:MM:SS).
            'start_time' => $this->start_time ? substr($this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr($this->end_time, 0, 5) : null,
        ];
    }
}
