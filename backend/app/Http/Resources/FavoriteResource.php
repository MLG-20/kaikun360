<?php

namespace App\Http\Resources;

use App\Support\Favoritables;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un favori POLYMORPHE (transversale, tous univers).
 *
 * Chaque favori expose son `type` (slug : property/stay/vehicle/experience/
 * mobility) et l'élément favorisé rendu par **sa propre ressource d'univers**
 * (`favoritable`) — le frontend obtient ainsi exactement la même forme que dans
 * le catalogue et réutilise le même mapping « carte ». `favoritable` est null si
 * l'élément a disparu (favori orphelin, filtré à l'affichage).
 *
 * @mixin \App\Models\Favorite
 */
class FavoriteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $favoritable = $this->whenLoaded('favoritable');

        return [
            'id' => $this->id,
            // Slug stable du type (déduit du nom de classe stocké).
            'type' => Favoritables::slugForClass($this->favoritable_type),
            'created_at' => $this->created_at,
            // L'élément favorisé, rendu par la ressource de son univers.
            'favoritable' => $this->when(
                $this->relationLoaded('favoritable') && $favoritable !== null,
                fn () => Favoritables::resourceFor($favoritable),
            ),
        ];
    }
}
