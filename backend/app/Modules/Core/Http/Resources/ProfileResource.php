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
            'verification_status' => $this->verification_status,
            'preferences' => $this->preferences,
        ];
    }
}
