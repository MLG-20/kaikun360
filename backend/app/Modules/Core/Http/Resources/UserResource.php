<?php

namespace App\Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un utilisateur (sans données sensibles).
 *
 * Le mot de passe et le remember_token ne sont jamais exposés (déjà masqués
 * sur le modèle via #[Hidden]). Le profil et les rôles ne sont inclus que
 * lorsqu'ils ont été chargés, pour éviter les requêtes N+1.
 *
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'city' => $this->city,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            // Noms des rôles Spatie (ex. ["client"]).
            'roles' => $this->getRoleNames(),
            // Profil inclus uniquement s'il a été explicitement chargé (->load('profile')).
            'profile' => ProfileResource::make($this->whenLoaded('profile')),
            'email_verified_at' => $this->email_verified_at,
            'phone_verified_at' => $this->phone_verified_at,
            'created_at' => $this->created_at,
        ];
    }
}
