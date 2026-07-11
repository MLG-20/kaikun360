<?php

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise à jour groupée des paramètres globaux (B13.4).
 *
 * Corps attendu : `{ "settings": { "<cle>": <valeur>, ... } }`. La validation
 * fine des clés autorisées et de leur type est faite dans le contrôleur (contre
 * SettingsRepository::DEFAULTS), car elle dépend du catalogue de réglages connu.
 */
class UpdateSettingsRequest extends FormRequest
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
            'settings' => ['required', 'array', 'min:1'],
        ];
    }
}
