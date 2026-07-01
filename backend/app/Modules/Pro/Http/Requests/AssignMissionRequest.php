<?php

namespace App\Modules\Pro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de l'affectation d'une mission à un prestataire
 * (POST /api/v1/providers/{provider}/missions).
 *
 * L'autorisation (admin) est vérifiée via la policy `assignMission`.
 */
class AssignMissionRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount_xof' => ['required', 'integer', 'min:0'],
            'client_id' => ['nullable', 'integer', 'exists:users,id'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
