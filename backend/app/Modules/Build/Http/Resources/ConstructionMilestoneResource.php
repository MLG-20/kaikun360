<?php

namespace App\Modules\Build\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un jalon de chantier (module Build).
 *
 * @mixin \App\Modules\Build\Models\ConstructionMilestone
 */
class ConstructionMilestoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'position' => $this->position,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'planned_date' => $this->planned_date?->toDateString(),
            'actual_date' => $this->actual_date?->toDateString(),
        ];
    }
}
