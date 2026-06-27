<?php

namespace App\Modules\Manage\Http\Requests;

use App\Modules\Manage\Enums\MandateStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de la création d'un mandat de gestion locative par un agent
 * (POST /api/v1/manage/mandates).
 *
 * L'autorisation fine (permission `gerer:gestion-locative`) est portée par le
 * middleware `can:` sur la route ; on revérifie ici par sécurité.
 */
class StoreMandateRequest extends FormRequest
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
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'commission_rate' => ['required', 'numeric', 'between:0,100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::in(MandateStatus::values())],
            'terms' => ['nullable', 'string'],
        ];
    }
}
