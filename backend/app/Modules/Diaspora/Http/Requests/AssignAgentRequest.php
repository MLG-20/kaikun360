<?php

namespace App\Modules\Diaspora\Http\Requests;

use App\Modules\Diaspora\Enums\DiasporaPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de l'affectation d'un agent à un projet diaspora
 * (PATCH /api/v1/diaspora-projects/{project}/assign).
 *
 * `agent_id` facultatif : si absent, l'agent le moins chargé est choisi
 * automatiquement (cf. AgentAssignmentService). `priority` permet d'ajuster la
 * priorité du dossier au passage (back-office). L'autorisation passe par la
 * policy `assign` (admin), vérifiée dans le contrôleur.
 */
class AssignAgentRequest extends FormRequest
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
            'agent_id' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['nullable', Rule::in(DiasporaPriority::values())],
        ];
    }
}
