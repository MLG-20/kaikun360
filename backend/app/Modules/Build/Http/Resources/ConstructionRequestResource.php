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
            // Date de dépôt : un dossier de suivi se lit d'abord par son
            // ancienneté (F7.3.b).
            'created_at' => $this->created_at?->toIso8601String(),
            // F7.3.b — LE DEMANDEUR. Le back-office pilote des dossiers, pas des
            // lignes anonymes : sans le nom et le contact du client, l'écran est
            // illisible et l'agent ne peut pas rappeler. Même correctif qu'en
            // F7.2.a sur la file de validation. `whenLoaded` → aucun N+1 dans
            // les vues qui ne chargent pas la relation.
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'email' => $this->client->email,
                'phone' => $this->client->phone,
            ]),
            'reports_count' => $this->whenCounted('reports'),
            // Nombre de jalons (compté par la supervision back-office qui fait
            // withCount(['milestones']) sans charger le détail). Absent des vues
            // qui ne comptent pas → la clé n'apparaît simplement pas.
            'milestones_count' => $this->whenCounted('milestones'),
            'milestones' => ConstructionMilestoneResource::collection($this->whenLoaded('milestones')),
            // Nombre de devis chiffrés (F7.3.e2) : la liste elle-même se charge à
            // part, comme les comptes rendus.
            'quotes_count' => $this->whenCounted('quotes'),
        ];
    }
}
