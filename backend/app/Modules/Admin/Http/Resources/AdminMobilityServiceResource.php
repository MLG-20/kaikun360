<?php

namespace App\Modules\Admin\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un trajet programmé pour la **supervision back-office**
 * (F7.2.j).
 *
 * Complète `MobilityServiceResource` (public) avec ce dont l'équipe a besoin
 * pour piloter l'exploitation : le **remplissage** du trajet (places prises /
 * restantes = les « disponibilités » du cahier des charges), le **véhicule**
 * affecté et le **prestataire** qui l'opère.
 *
 * `seats_taken` est calculé par le contrôleur (`withSum` sur les réservations
 * non annulées) et déposé sur le modèle ; on ne le recalcule pas ici pour
 * éviter une requête par ligne.
 *
 * @mixin \App\Modules\Mobility\Models\MobilityService
 */
class AdminMobilityServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Places prises : somme des participants des réservations non annulées,
        // agrégée en amont. Absente = 0 (trajet jamais réservé).
        $taken = (int) ($this->seats_taken ?? 0);
        $capacity = (int) $this->capacity;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'departure' => $this->departure,
            'destination' => $this->destination,
            'departure_at' => $this->departure_at?->toIso8601String(),
            'capacity' => $capacity,
            'price_xof' => $this->price_xof,
            'description' => $this->description,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),

            // --- Remplissage (supervision).
            'seats_taken' => $taken,
            'seats_left' => max(0, $capacity - $taken),

            // --- Véhicule affecté (facultatif : un trajet peut être annoncé
            // sans véhicule nommément rattaché).
            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle ? [
                'id' => $this->vehicle->id,
                'reference' => $this->vehicle->reference,
                'label' => trim(($this->vehicle->brand ?? '').' '.($this->vehicle->model ?? '')) ?: null,
                'type_label' => $this->vehicle->type?->label(),
                'capacity' => $this->vehicle->capacity,
            ] : null),

            // --- Prestataire opérateur.
            'provider' => $this->whenLoaded('provider', fn () => [
                'id' => $this->provider->id,
                'name' => $this->provider->name,
                'email' => $this->provider->email,
                'phone' => $this->provider->phone,
            ]),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
