<?php

namespace App\Modules\Manage\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de la création d'un incident (POST .../mandates/{mandate}/incidents).
 *
 * L'incident est rattaché au bien du mandat ; `property_id` est donc déduit du
 * mandat côté contrôleur (pas saisi par le client).
 */
class StoreIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gerer:gestion-locative') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', Rule::in(['p1', 'p2', 'p3', 'p4'])],
        ];
    }
}
