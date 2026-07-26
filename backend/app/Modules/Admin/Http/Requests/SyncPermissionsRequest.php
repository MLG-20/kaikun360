<?php

namespace App\Modules\Admin\Http\Requests;

use App\Modules\Admin\Enums\AdminPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Délégation des permissions d'un membre de l'équipe (F7.1.b).
 *
 * Le super administrateur / l'administrateur définit l'ENSEMBLE des dossiers
 * qu'un sous-admin a le droit de traiter (remplacement complet : les cases
 * cochées = la liste envoyée). Seules les 12 permissions **délégables** sont
 * acceptées (`consulter:dashboard-admin`, l'accès de base, n'en fait pas partie).
 *
 * Le garde-fou d'escalade (les permissions de gouvernance exigent un super_admin)
 * est appliqué dans le contrôleur car il dépend de l'acteur.
 */
class SyncPermissionsRequest extends FormRequest
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
            // Tableau (éventuellement vide = on retire tous les droits de traitement).
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(AdminPermission::delegable())],
        ];
    }
}
