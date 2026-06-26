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
                'tourist_zone' => $this->tourist_zone,
                'address' => $this->address,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ],
            'owner' => [
                'id' => $this->owner_id,
                'name' => $this->owner?->name,
            ],
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
        ];
    }
}
