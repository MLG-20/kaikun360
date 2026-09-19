<?php

namespace App\Modules\Manage\Http\Requests;

use App\Modules\Manage\Enums\MandateStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification d'un mandat de gestion (PATCH /api/v1/manage/mandates/{mandate}).
 *
 * Le bien et le propriétaire ne changent jamais : un mandat désigne UN bien, en
 * changer reviendrait à en ouvrir un autre. Seuls les termes évoluent.
 */
class UpdateMandateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('mandate')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'commission_rate' => ['sometimes', 'required', 'numeric', 'between:0,100'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', 'required', Rule::in(MandateStatus::values())],
            'terms' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
