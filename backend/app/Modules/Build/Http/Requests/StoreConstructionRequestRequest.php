<?php

namespace App\Modules\Build\Http\Requests;

use App\Modules\Build\Enums\ConstructionObjective;
use App\Modules\Build\Enums\FinishLevel;
use App\Modules\Build\Models\ConstructionRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation du dépôt d'une demande de construction
 * (POST /api/v1/construction-requests).
 */
class StoreConstructionRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ConstructionRequest::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'objective' => ['required', Rule::in(ConstructionObjective::values())],
            'city' => ['required', 'string', 'max:255'],
            'surface_m2' => ['required', 'integer', 'min:1'],
            'budget_xof' => ['nullable', 'integer', 'min:0'],
            'finish_level' => ['required', Rule::in(FinishLevel::values())],
            'description' => ['nullable', 'string'],
        ];
    }
}
