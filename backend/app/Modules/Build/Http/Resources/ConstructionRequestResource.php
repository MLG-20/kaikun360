<?php

namespace App\Modules\Build\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'une demande de construction (module Build).
 *
 * Les jalons sont embarqués lorsqu'ils ont été chargés (`whenLoaded`) ; le
 * nombre de rapports apparaît s'il a été compté (`withCount`).
 *
 * @mixin \App\Modules\Build\Models\ConstructionRequest
 */
class ConstructionRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'objective' => $this->objective?->value,
            'objective_label' => $this->objective?->label(),
            'city' => $this->city,
            'surface_m2' => $this->surface_m2,
            'budget_xof' => $this->budget_xof,
            'finish_level' => $this->finish_level?->value,
            'finish_level_label' => $this->finish_level?->label(),
            'description' => $this->description,
            'estimated_cost_xof' => $this->estimated_cost_xof,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'reports_count' => $this->whenCounted('reports'),
            'milestones' => ConstructionMilestoneResource::collection($this->whenLoaded('milestones')),
        ];
    }
}
