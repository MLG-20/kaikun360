<?php

namespace App\Modules\Diaspora\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un projet diaspora (module Diaspora).
 *
 * @mixin \App\Modules\Diaspora\Models\DiasporaProject
 */
class DiasporaProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'project_type' => $this->project_type?->value,
            'project_type_label' => $this->project_type?->label(),
            'residence_country' => $this->residence_country,
            'budget_xof' => $this->budget_xof,
            'description' => $this->description,
            'priority' => $this->priority?->value,
            'priority_label' => $this->priority?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'agent_id' => $this->agent_id,
            'reports_count' => $this->whenCounted('reports'),
            // Client à l'origine du dossier (utile à la file/fiche back-office).
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'email' => $this->client->email,
                'phone' => $this->client->phone,
            ]),
            // Agent dédié affecté (null tant que non affecté).
            'agent' => $this->whenLoaded('agent', fn () => $this->agent ? [
                'id' => $this->agent->id,
                'name' => $this->agent->name,
                'email' => $this->agent->email,
            ] : null),
        ];
    }
}
