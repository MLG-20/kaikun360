<?php

namespace App\Modules\Admin\Http\Requests;

use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un membre de l'équipe back-office (F7.1.a).
 *
 * Permet de changer le rôle (agent ↔ admin) et/ou le statut d'un employé
 * (actif / suspendu / désactivé). Au moins l'un des deux est requis. Les
 * garde-fous de hiérarchie (escalade, auto-modification, protection des
 * super_admin) sont appliqués dans le contrôleur car ils dépendent de l'acteur
 * et de la cible.
 */
class UpdateTeamMemberRequest extends FormRequest
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
            'role' => [
                'required_without:status',
                'string',
                Rule::in(UserRole::assignableStaff()),
            ],
            'status' => [
                'required_without:role',
                'string',
                // On ne remet pas un employé « en attente de vérification » : les
                // statuts pilotables sont actif / suspendu / désactivé.
                Rule::in([
                    UserStatus::ACTIF->value,
                    UserStatus::SUSPENDU->value,
                    UserStatus::DESACTIVE->value,
                ]),
            ],
        ];
    }
}
