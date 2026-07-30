<?php

namespace App\Modules\Core\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un utilisateur (sans données sensibles).
 *
 * Le mot de passe et le remember_token ne sont jamais exposés (déjà masqués
 * sur le modèle via #[Hidden]). Le profil et les rôles ne sont inclus que
 * lorsqu'ils ont été chargés, pour éviter les requêtes N+1.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Faut-il joindre les permissions back-office ? (F7.4.a, corrigé en F7.4.e)
     *
     * ⚠️ Opt-in EXPLICITE, et surtout pas une déduction du genre
     * « `$request->user()->id === $this->id` ». C'était la première version, et
     * elle échouait exactement là où ça comptait : sur les réponses de
     * **connexion** (`/auth/login`, `/auth/two-factor`), la requête n'est pas
     * encore authentifiée — `$request->user()` y est `null`. Le compte recevait
     * donc un jeton sans ses permissions, et le rail du back-office s'affichait
     * amputé jusqu'au rechargement suivant.
     *
     * Rester en opt-in protège ce qui avait motivé la restriction : cette
     * ressource sert aussi aux annuaires admin, où une requête de permissions
     * par ligne ferait un N+1 et où les droits d'un collègue n'ont rien à faire.
     */
    private bool $withPermissions = false;

    /**
     * Joint les permissions back-office à la représentation.
     *
     * À n'appeler que sur une ressource qui représente **le compte connecté
     * lui-même** : réponses d'authentification, `/users/me`, mise à jour de son
     * propre profil. Jamais dans une liste.
     */
    public function withPermissions(): static
    {
        $this->withPermissions = true;

        return $this;
    }

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
            'address' => $this->address,
            // Localisation structurée (F3.2b) : identifiants pour préremplir les
            // menus déroulants en cascade + noms lisibles quand chargés.
            'region_id' => $this->region_id,
            'department_id' => $this->department_id,
            'commune_id' => $this->commune_id,
            'region' => $this->whenLoaded('region', fn () => $this->region?->name),
            'department' => $this->whenLoaded('department', fn () => $this->department?->name),
            'commune' => $this->whenLoaded('commune', fn () => $this->commune?->name),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            // Noms des rôles Spatie (ex. ["client"]).
            'roles' => $this->getRoleNames(),
            // Permissions back-office (F7.4.a) — sur demande explicite
            // (`->withPermissions()`) et pour l'équipe seulement. Le rail du
            // back-office s'en sert pour n'afficher que les rubriques ouvertes.
            'permissions' => $this->when(
                $this->withPermissions && $this->estStaff(),
                fn () => $this->permissionsBackOffice(),
            ),
            // Profil inclus uniquement s'il a été explicitement chargé (->load('profile')).
            'profile' => ProfileResource::make($this->whenLoaded('profile')),
            'email_verified_at' => $this->email_verified_at,
            'phone_verified_at' => $this->phone_verified_at,
            'created_at' => $this->created_at,
        ];
    }
}
