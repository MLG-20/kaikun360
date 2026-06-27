<?php

namespace App\Modules\Manage\Http\Requests;

use App\Modules\Manage\Enums\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de la création d'une dépense (POST .../mandates/{mandate}/expenses).
 *
 * Le `property_id` est déduit du mandat côté contrôleur. Un incident peut être
 * rattaché s'il appartient au même bien (vérifié dans le contrôleur).
 */
class StoreExpenseRequest extends FormRequest
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
            'incident_id' => ['nullable', 'integer', 'exists:incidents,id'],
            'label' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(ExpenseCategory::values())],
            'amount_xof' => ['required', 'integer', 'min:0'],
            'spent_at' => ['required', 'date'],
        ];
    }
}
