<?php

namespace App\Modules\Diaspora\Http\Requests;

use App\Modules\Diaspora\Enums\DiasporaPriority;
use App\Modules\Diaspora\Enums\DiasporaProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation du pilotage back-office d'un dossier diaspora — statut et/ou
 * priorité (PATCH /api/v1/diaspora-projects/{project}) — F7.2.i.
 *
 * Au moins l'un des deux champs doit être fourni. L'autorisation (agent affecté
 * ou admin) est vérifiée via la policy `update` dans le contrôleur.
 */
class UpdateDiasporaProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required_without:priority', 'nullable', Rule::in(DiasporaProjectStatus::values())],
            'priority' => ['required_without:status', 'nullable', Rule::in(DiasporaPriority::values())],
        ];
    }
}
