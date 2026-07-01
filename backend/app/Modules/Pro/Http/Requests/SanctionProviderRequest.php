<?php

namespace App\Modules\Pro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation d'un avertissement/sanction prestataire (charte qualité).
 *
 * L'autorisation (agent habilité) est portée par le middleware `can:` sur la route.
 */
class SanctionProviderRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
