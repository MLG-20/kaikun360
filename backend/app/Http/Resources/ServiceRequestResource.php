<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'une demande client générique (couche transversale).
 *
 * @mixin \App\Models\ServiceRequest
 */
class ServiceRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'service_type' => $this->service_type?->value,
            'service_type_label' => $this->service_type?->label(),
            'message' => $this->message,
            'budget_xof' => $this->budget_xof,
            'city' => $this->city,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'priority' => $this->priority?->value,
            'created_at' => $this->created_at,
            // Transitions autorisées depuis le statut courant (aide au front).
            'allowed_transitions' => collect($this->status?->allowedNext() ?? [])
                ->map(fn ($s) => $s->value)->all(),
        ];
    }
}
