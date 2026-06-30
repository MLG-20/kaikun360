<?php

namespace App\Modules\Diaspora\Http\Requests;

use App\Modules\Diaspora\Enums\DiasporaPriority;
use App\Modules\Diaspora\Enums\DiasporaProjectType;
use App\Modules\Diaspora\Models\DiasporaProject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation du dépôt d'un projet diaspora (POST /api/v1/diaspora-projects).
 */
class StoreDiasporaProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', DiasporaProject::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_type' => ['required', Rule::in(DiasporaProjectType::values())],
            'residence_country' => ['required', 'string', 'max:255'],
            'budget_xof' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', Rule::in(DiasporaPriority::values())],
        ];
    }
}
