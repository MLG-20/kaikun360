<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la création d'un devis pour une demande
 * (POST /api/v1/requests/{request}/quotes).
 *
 * L'autorisation (agents/admin) est portée par le middleware `can:traiter:demandes`.
 */
class StoreQuoteRequest extends FormRequest
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
            'amount_xof' => ['required', 'integer', 'min:0'],
            'details' => ['nullable', 'array'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
