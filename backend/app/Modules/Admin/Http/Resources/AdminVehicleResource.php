<?php

namespace App\Modules\Admin\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un véhicule pour la **supervision back-office** (F7.2.j).
 *
 * Le catalogue public (`VehicleResource`) n'expose que ce dont un visiteur a
 * besoin. L'écran Mobilité du back-office, lui, doit contrôler la conformité de
 * la flotte : c'est ici qu'on ajoute les champs de contrôle que le catalogue
 * public ne montre pas — référence d'**assurance**, identité du **chauffeur**,
 * gilets de sauvetage (pirogues) et les deux drapeaux de conformité.
 *
 * ⚠️ Cette Resource n'est servie que derrière les gardes `admin` : ces champs
 * sont des données de contrôle, pas des données publiques.
 *
 * @mixin \App\Modules\Mobility\Models\Vehicle
 */
class AdminVehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // --- Socle identique au catalogue public (l'écran Catalogues
            // F7.2.b consomme la même route : le format reste un sur-ensemble).
            'id' => $this->id,
            'reference' => $this->reference,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'brand' => $this->brand,
            'model' => $this->model,
            'capacity' => $this->capacity,
            'price_per_day_xof' => $this->price_per_day_xof,
            'has_driver' => $this->has_driver,
            'caution_xof' => $this->caution_xof,
            'description' => $this->description,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'published_at' => $this->published_at?->toIso8601String(),

            // --- Contrôle de conformité (back-office uniquement).
            'insurance_ref' => $this->insurance_ref,
            'driver_identity' => $this->driver_identity,
            'life_jackets_count' => $this->life_jackets_count,
            'weather_compliant' => $this->weather_compliant,
            'provider_compliant' => $this->provider_compliant,

            // --- Le prestataire propriétaire (pour joindre en cas d'anomalie).
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
