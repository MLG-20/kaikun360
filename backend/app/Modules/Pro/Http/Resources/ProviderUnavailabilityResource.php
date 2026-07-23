<?php

namespace App\Modules\Pro\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'une période d'indisponibilité (module Pro, F5.4).
 *
 * @mixin \App\Modules\Pro\Models\ProviderUnavailability
 */
class ProviderUnavailabilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'reason' => $this->reason,
        ];
    }
}
