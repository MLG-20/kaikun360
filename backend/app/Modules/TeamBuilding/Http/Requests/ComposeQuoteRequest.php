<?php

namespace App\Modules\TeamBuilding\Http\Requests;

use App\Modules\TeamBuilding\Enums\QuoteLineCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de la composition d'un devis
 * (POST /api/v1/team-building-requests/{request}/quotes).
 *
 * L'autorisation (admin) est vérifiée via la policy `manage` dans le contrôleur.
 */
class ComposeQuoteRequest extends FormRequest
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
            'margin_rate' => ['nullable', 'numeric', 'between:0,100'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.category' => ['required', Rule::in(QuoteLineCategory::values())],
            'components.*.label' => ['nullable', 'string', 'max:255'],
            'components.*.module' => ['nullable', 'string', 'max:255'],
            'components.*.quantity' => ['required', 'integer', 'min:1'],
            'components.*.unit_price_xof' => ['required', 'integer', 'min:0'],
        ];
    }
}
