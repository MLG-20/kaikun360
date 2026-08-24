<?php

namespace App\Modules\Mobility\Http\Resources;

use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Modules\Mobility\Models\MobilityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Représentation JSON d'un service de mobilité (module Mobility).
 *
 * @mixin MobilityService
 */
class MobilityServiceResource extends JsonResource
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
            'departure' => $this->departure,
            'destination' => $this->destination,
            'departure_at' => $this->departure_at?->toIso8601String(),
            'capacity' => $this->capacity,
            'price_xof' => $this->price_xof,
            'description' => $this->description,
            // Lien Google Maps du point de départ, collé par le prestataire (F5.10).
            'maps_link' => $this->maps_link,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            // F8.23 — le véhicule affecté, en clair. ⚠️ Sans lui, le formulaire
            // de correction du prestataire ne pouvait pas se préremplir : il
            // aurait remis « aucun véhicule » à chaque retouche, détachant en
            // silence le départ de son illustration et de sa conformité.
            'vehicle_id' => $this->vehicle_id,
            // F8.18 — LES PHOTOS D'UN TRAJET SONT CELLES DE SON VÉHICULE.
            //
            // ⚠️ Choix d'architecture, et il évite une table de plus : un trajet
            // est **opéré par un véhicule** (`mobility_services.vehicle_id`), et
            // ce que le client veut voir avant de monter, c'est précisément ce
            // véhicule. Donner au trajet sa propre galerie obligerait le
            // prestataire à re-téléverser les mêmes photos du même minibus à
            // chaque départ programmé — il ne le ferait pas, et les cartes
            // resteraient vides. C'est exactement le lien nuitée → bien.
            //
            // ⚠️ Conséquence assumée : un trajet **sans véhicule rattaché**
            // (`vehicle_id` est nullable) n'a pas de photo. Le repli dégradé du
            // catalogue reste donc légitime pour ce cas-là, et lui seul.
            // ⚠️ **Ni `whenLoaded`, ni `when` ici, et c'est délibéré** : sur une
            // relation chargée mais VIDE (`vehicle_id` null), `whenLoaded` rend
            // un `MissingValue` que `MediaResource::collection()` ne sait pas
            // traiter — le catalogue entier répondait 500 à cause d'un seul
            // trajet sans véhicule. Les deux clés sont donc toujours présentes,
            // et valent une liste vide / `null` quand il n'y a pas de photo.
            // L'écran a besoin de cette franchise : une clé absente ne se
            // distingue pas d'une réponse incomplète.
            'photos' => MediaResource::collection($this->photosHeritees()),
            'photo_url' => $this->photosHeritees()->first()?->resolveUrl(),
        ];
    }

    /**
     * Les photos du véhicule qui opère ce trajet — vide s'il n'y en a pas.
     *
     * Ne déclenche **aucune requête** : on ne lit que ce que le contrôleur a
     * chargé (`with('vehicle.media')`). Un trajet servi sans cette relation
     * apparaîtra donc sans photo plutôt que de provoquer un N+1 silencieux sur
     * une liste paginée.
     *
     * @return Collection<int, Media>
     */
    private function photosHeritees(): Collection
    {
        if (! $this->resource->relationLoaded('vehicle')) {
            return collect();
        }

        $vehicle = $this->resource->vehicle;

        if ($vehicle === null || ! $vehicle->relationLoaded('media')) {
            return collect();
        }

        return $vehicle->media;
    }
}
