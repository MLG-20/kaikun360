<?php

namespace App\Modules\TeamBuilding\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'une demande de team building (module Team Building).
 *
 * @mixin \App\Modules\TeamBuilding\Models\TeamBuildingRequest
 */
class TeamBuildingRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'participants' => $this->participants,
            'city' => $this->city,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'budget_xof' => $this->budget_xof,
            'needs' => $this->needs ?? [],
            'description' => $this->description,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'quotes' => TeamBuildingQuoteResource::collection($this->whenLoaded('quotes')),
        ];
    }
}
