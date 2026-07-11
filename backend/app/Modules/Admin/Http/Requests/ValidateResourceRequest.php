<?php

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la décision de modération générique (B13.2).
 *
 * L'autorisation fine (permission par type de ressource) est vérifiée dans le
 * contrôleur, car elle dépend du `{type}` de l'URL — d'où `authorize()` = true.
 */
class ValidateResourceRequest extends FormRequest
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
            // approve = valider/publier ; reject = refuser.
            'decision' => ['required', 'string', 'in:approve,reject'],
            // Motif facultatif, tracé dans le journal d'activité (surtout un refus).
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
