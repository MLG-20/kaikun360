<?php

namespace App\Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un profil utilisateur.
 *
 * @mixin \App\Modules\Core\Models\Profile
 */
class ProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            // Photo de profil / logo d'entreprise (F8.0). `null` tant que le
            // compte n'en a pas déposé : l'interface retombe alors sur
            // l'initiale du nom.
            'avatar_url' => $this->avatarUrl(),
            // `photo` ou `logo` — dit à l'interface quoi demander, et comment
            // afficher l'image (un logo se pose dans un cadre, un visage se
            // recadre en rond).
            'avatar_kind' => $this->avatarKind(),
            'verification_status' => $this->verification_status,
            'preferences' => $this->preferences,
        ];
    }
}
