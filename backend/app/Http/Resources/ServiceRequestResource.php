<?php

namespace App\Http\Resources;

use App\Models\ServiceRequest;
use App\Support\Trash\PersonalHiding;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'une demande client générique (couche transversale).
 *
 * @mixin ServiceRequest
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
            // F11.5 — le client peut-il ranger cette demande dans sa corbeille ?
            // Miroir EXACT de `PersonalHiding::raisonDeBlocage()` : le bouton
            // n'apparaît que là où le serveur dirait oui. Le front n'a jamais à
            // rejouer la règle « seule une demande clôturée se range », qui n'a
            // qu'une seule écriture.
            'hideable' => $request->user()
                ? (new PersonalHiding)->estRangeable($this->resource, $request->user())
                : false,
            // Transitions autorisées depuis le statut courant (aide au front).
            'allowed_transitions' => collect($this->status?->allowedNext() ?? [])
                ->map(fn ($s) => $s->value)->all(),
        ];
    }
}
