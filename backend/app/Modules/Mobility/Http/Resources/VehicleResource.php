<?php

namespace App\Modules\Mobility\Http\Resources;

use App\Http\Resources\MediaResource;
use App\Modules\Mobility\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un véhicule (module Mobility).
 *
 * @mixin Vehicle
 */
class VehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
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
            // Lien Google Maps collé par le prestataire (F5.10).
            'maps_link' => $this->maps_link,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'published_at' => $this->published_at?->toIso8601String(),
            // F8.18 — LES PHOTOS, absentes depuis l'origine côté API.
            //
            // Le modèle porte `HasMedia` depuis F8.1 et `POST /media/upload`
            // accepte la clé `vehicle` depuis B12.1 : ce qui manquait, c'était
            // le chemin de retour. Un véhicule pouvait donc être illustré sans
            // qu'aucun écran public ne puisse le montrer — la carte du catalogue
            // tombait invariablement sur sa vignette de repli.
            //
            // ⚠️ `whenLoaded` et non un accès direct : la clé disparaît quand la
            // relation n'est pas chargée, plutôt que de déclencher une requête
            // par ligne de catalogue (N+1 sur la liste la plus consultée).
            'photos' => MediaResource::collection($this->whenLoaded('media')),
            // Raccourci consommé par les CARTES : l'image de couverture seule.
            // `media()` trie déjà « principale d'abord », `first()` est donc la
            // couverture, et `null` quand le véhicule n'a pas encore de photo.
            'photo_url' => $this->when(
                $this->relationLoaded('media'),
                fn () => $this->media->first()?->resolveUrl(),
            ),
        ];
    }
}
