<?php

namespace App\Modules\Admin\Http\Requests;

use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un compte par le back-office (B13.3).
 *
 * Permet de changer le rôle et/ou le statut d'un utilisateur, ou d'accorder
 * l'accès anticipé (2026-08-14, fermeture avant ouverture). Au moins l'un des
 * trois doit être fourni. Les garde-fous d'escalade de privilèges et
 * d'auto-modification sont appliqués dans le contrôleur, car ils dépendent de
 * l'acteur et de la cible ; `early_access` y est en plus réservé au super_admin.
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
                'required_without_all:status,early_access',
                'string',
                Rule::in(array_values(array_diff(UserRole::values(), [UserRole::VISITEUR->value]))),
            ],
            // Statut du compte (actif / suspendu / désactivé / en attente).
            'status' => [
                'required_without_all:role,early_access',
                'string',
                Rule::in(UserStatus::values()),
            ],
            // Accès anticipé pendant la fermeture (2026-08-14).
            'early_access' => [
                'required_without_all:role,status',
                'boolean',
            ],
        ];
    }
}
