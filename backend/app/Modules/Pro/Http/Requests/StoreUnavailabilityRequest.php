<?php

namespace App\Modules\Pro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation d'une période d'indisponibilité
 * (POST /api/v1/providers/availability/unavailability).
 *
 * Plage de dates incluses avec un motif facultatif. L'accès est garanti par le
 * contrôleur (résolution du profil prestataire du compte connecté).
 */
class StoreUnavailabilityRequest extends FormRequest
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
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
