<?php

namespace App\Http\Requests;

use App\Enums\QuoteStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de la réponse à un devis (PATCH /api/v1/quotes/{quote}).
 *
 * Le demandeur accepte ou refuse ; l'autorisation passe par la policy `respond`.
 */
class RespondQuoteRequest extends FormRequest
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
            'status' => ['required', Rule::in([QuoteStatus::ACCEPTE->value, QuoteStatus::REFUSE->value])],
        ];
    }
}
