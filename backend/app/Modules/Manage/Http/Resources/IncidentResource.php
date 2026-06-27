<?php

namespace App\Modules\Manage\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un incident lié à un bien (module Manage).
 *
 * @mixin \App\Modules\Manage\Models\Incident
 */
class IncidentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'property_id' => $this->property_id,
            'reported_by' => $this->reported_by,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
        ];
    }
}
