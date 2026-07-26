<?php

namespace App\Modules\Admin\Http\Resources;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un membre de l'équipe back-office (F7.1).
 *
 * Vue « employé » du poste de commandement : contrairement à `UserResource`
 * (orientée compte public), on expose ici le rôle **principal** (agent / admin /
 * super_admin) et la liste des **permissions effectives** de la personne — ce
 * que le super administrateur pilote (F7.1.b, délégation des dossiers à traiter).
 *
 * Aucune donnée sensible : le mot de passe reste masqué par le modèle.
 *
 * @mixin User
 */
class TeamMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Rôle back-office principal (un employé n'en porte qu'un côté Kaikun).
        $role = $this->getRoleNames()
            ->first(fn (string $name) => in_array($name, UserRole::staff(), true));

        $roleEnum = $role !== null ? UserRole::tryFrom($role) : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $role,
            'role_label' => $roleEnum?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            // Permissions effectives (rôle + délégations directes), triées pour un
            // affichage stable de la matrice de droits côté back-office.
            'permissions' => $this->getPermissionNames()->sort()->values(),
            // Permissions DIRECTES = les dossiers délégués à cette personne (F7.1.b) :
            // ce sont les cases cochées de la matrice (hors accès de base du rôle).
            'direct_permissions' => $this->whenLoaded(
                'permissions',
                fn () => $this->getDirectPermissions()->pluck('name')->sort()->values(),
            ),
            // Le compte a-t-il déjà défini son mot de passe (invitation honorée) ?
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
        ];
    }
}
