<?php

namespace App\Modules\Immo\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un bien immobilier (catalogue public et détail).
 *
 * N'expose AUCUNE donnée sensible du propriétaire (seulement id + nom).
 * La localisation est restituée via le référentiel (noms région/département/commune).
 *
 * @mixin \App\Modules\Immo\Models\Property
 */
class PropertyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'price_xof' => $this->price_xof,
            'status' => $this->status?->value,
            'verification_level' => $this->verification_level,
            'location' => [
                // Les relations sont chargées en amont par le contrôleur (pas de N+1).
                'region' => $this->region?->name,
                'department' => $this->department?->name,
                'commune' => $this->commune?->name,
                // Les identifiants bruts du référentiel : nécessaires au formulaire
                // d'édition du propriétaire (F4.3) pour préremplir les sélecteurs en
                // cascade. Inoffensifs côté public (simples ids référentiels).
                'region_id' => $this->region_id,
                'department_id' => $this->department_id,
                'commune_id' => $this->commune_id,
                'tourist_zone' => $this->tourist_zone,
                'address' => $this->address,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ],
            'owner' => [
                'id' => $this->owner_id,
                'name' => $this->owner?->name,
            ],
            // Photos du bien (relation triée : principale d'abord). Exposées dès
            // que la relation est chargée — catalogue public, fiche publique et
            // gestion privée la chargent toutes. `photo_url` est le raccourci
            // consommé par les cartes du catalogue (image de couverture) ; il
            // vaut null si le bien n'a pas encore de photo, auquel cas le front
            // retombe sur son visuel de repli.
            'photos' => \App\Http\Resources\MediaResource::collection($this->whenLoaded('media')),
            'photo_url' => $this->when(
                $this->relationLoaded('media'),
                fn () => $this->media->first()?->resolveUrl(),
            ),
            // Compteurs de supervision (F8.1), présents seulement quand la
            // requête back-office les a agrégés (`withCount`). Permettent de
            // repérer une annonce publiée sans visuel, ou dont des photos ont
            // été masquées par la modération.
            'media_count' => $this->when(
                $this->media_count !== null,
                fn () => (int) $this->media_count,
            ),
            'media_hidden_count' => $this->when(
                $this->media_hidden_count !== null,
                fn () => (int) $this->media_hidden_count,
            ),
            // Config « nuitées » — présente seulement pour la gestion privée
            // (chargée par PropertyManagementController) : permet à la fiche et
            // au formulaire d'édition du propriétaire de connaître le mode de
            // location (mensuelle / nuitées / mixte). On teste `relationLoaded`
            // pour (1) ne PAS déclencher de N+1 côté catalogue public — où la
            // relation n'est pas chargée, la clé est simplement absente — et
            // (2) restituer `null` proprement quand le bien n'a pas de nuitées.
            'stay' => $this->when(
                $this->relationLoaded('stay'),
                fn () => $this->stay
                    ? \App\Modules\Stay\Http\Resources\StayResource::make($this->stay)
                    : null,
            ),
            // Nombre de pièces justificatives — présent seulement quand la liste
            // « Mes biens » a demandé le compteur (`withCount('documents')`) pour
            // l'écran « Documents » (F4.5). `whenCounted` garde la clé absente
            // ailleurs (catalogue public, fiches) : aucune requête superflue.
            'documents_count' => $this->whenCounted('documents'),
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
        ];
    }
}
