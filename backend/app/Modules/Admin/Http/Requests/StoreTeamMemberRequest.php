<?php

namespace App\Modules\Admin\Http\Requests;

use App\Modules\Core\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création (invitation) d'un membre de l'équipe back-office (F7.1.a).
 *
 * Le super administrateur / l'administrateur enrôle un employé (agent ou admin).
 * L'invité recevra un code par e-mail pour définir lui-même son mot de passe
 * (flux de réinitialisation existant) : aucun mot de passe n'est saisi ici.
 *
 * L'autorisation d'accès à la route est portée par `can:gerer:utilisateurs` ;
 * le garde-fou d'escalade (seul un super_admin crée un admin) est vérifié dans
 * le contrôleur car il dépend de l'acteur.
 */
class StoreTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            // E-mail = identifiant de connexion : obligatoire et unique.
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            // Téléphone optionnel mais unique s'il est fourni.
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('users', 'phone')],
            // Rôle attribuable à un employé : agent (sous-admin) ou admin.
            // `super_admin` n'est jamais attribuable via l'interface.
            'role' => ['required', 'string', Rule::in(UserRole::assignableStaff())],
        ];
    }
}
