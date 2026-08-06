<?php

namespace App\Modules\Explore\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la mise à jour d'un circuit (PATCH /api/v1/experiences/{id}).
 *
 * Miroir de `StoreExperienceRequest`, en `sometimes` : une modification partielle
 * ne doit pas obliger à renvoyer tout le formulaire.
 *
 * L'autorisation (prestataire propriétaire ou admin) est vérifiée dans le
 * contrôleur via la policy `update` — comme `UpdateVehicleRequest`. Le statut
 * n'est pas modifiable ici : il évolue par la validation d'un agent.
 */
class UpdateExperienceRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'destination' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'duration_days' => ['sometimes', 'integer', 'min:1'],
            'price_xof' => ['sometimes', 'integer', 'min:0'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'inclusions' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
