<?php

namespace App\Modules\Build\Http\Requests;

use App\Modules\Build\Enums\ConstructionLot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de la composition d'un devis de chantier
 * (POST /api/v1/construction-requests/{id}/quotes) — F7.3.e2.
 *
 * Réservé à la permission `gerer:chantiers` (middleware `can:` sur la route).
 */
class ComposeConstructionQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gerer:chantiers') ?? false;
    }

    /**
     * `margin_rate` omis → le réglage `build.margin_rate` du back-office s'applique.
     * Une quantité décimale est acceptée (18,5 m² de carrelage).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'margin_rate' => ['nullable', 'numeric', 'between:0,100'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.lot' => ['required', Rule::in(ConstructionLot::values())],
            'lines.*.label' => ['nullable', 'string', 'max:255'],
            'lines.*.unit' => ['nullable', 'string', 'max:30'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price_xof' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Un devis doit contenir au moins une ligne.',
            'valid_until.after_or_equal' => 'Un devis ne peut pas être valable jusqu’à une date passée.',
        ];
    }
}
