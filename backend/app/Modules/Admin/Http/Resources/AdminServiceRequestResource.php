<?php

namespace App\Modules\Admin\Http\Resources;

use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'une demande client pour la **file de traitement du
 * back-office** (F8.9).
 *
 * `ServiceRequestResource` sert l'espace client : le demandeur y lit *sa*
 * demande, il sait donc déjà qui il est. La file de traitement, elle, est
 * transverse — l'agent doit pouvoir **rappeler le client** sans quitter
 * l'écran. D'où le bloc `requester` (identité + contact), absent de la
 * ressource publique.
 *
 * ⚠️ Servie uniquement derrière la garde `traiter:demandes` : le contact d'un
 * client n'a rien à faire dans une réponse publique.
 *
 * @mixin ServiceRequest
 */
class AdminServiceRequestResource extends JsonResource
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
            'priority_label' => $this->priority?->label(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Les prochaines étapes permises par la machine à états. C'est le
            // serveur qui les calcule : l'écran ne doit jamais proposer un
            // bouton qui se ferait refuser en 422.
            'allowed_transitions' => collect($this->status?->allowedNext() ?? [])
                ->map(fn ($status) => ['value' => $status->value, 'label' => $status->label()])
                ->all(),

            // Le demandeur — la raison d'être de cet écran : traiter une
            // demande commence presque toujours par joindre son auteur.
            'requester' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'city' => $this->user->city,
            ]),

            // Nombre de devis déjà proposés : une demande « au stade devis »
            // sans aucun devis attaché est une anomalie qui doit se voir dès
            // la liste.
            'quotes_count' => $this->whenCounted('quotes'),
        ];
    }
}
