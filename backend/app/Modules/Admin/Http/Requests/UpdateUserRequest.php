<?php

namespace App\Modules\Admin\Http\Requests;

use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un compte par le back-office (B13.3).
 *
 * Permet de changer le rôle et/ou le statut d'un utilisateur. Au moins l'un des
 * deux doit être fourni (`required_without`). Les garde-fous d'escalade de
 * privilèges et d'auto-modification sont appliqués dans le contrôleur, car ils
 * dépendent de l'acteur et de la cible.
 */
class UpdateUserRequest extends FormRequest
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
            // Rôle Spatie cible. `visiteur` est conceptuel et non assignable.
            'role' => [
                'required_without:status',
                'string',
                Rule::in(array_values(array_diff(UserRole::values(), [UserRole::VISITEUR->value]))),
            ],
            // Statut du compte (actif / suspendu / désactivé / en attente).
            'status' => [
                'required_without:role',
                'string',
                Rule::in(UserStatus::values()),
            ],
        ];
    }
}
