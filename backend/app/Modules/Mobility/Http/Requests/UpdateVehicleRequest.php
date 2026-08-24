<?php

namespace App\Modules\Mobility\Http\Requests;

use App\Rules\GoogleMapsLink;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la mise à jour d'un véhicule (PATCH /api/v1/vehicles/{vehicle}).
 *
 * L'autorisation (prestataire propriétaire ou admin) est vérifiée dans le
 * contrôleur via la policy `update`. Le statut n'est pas modifiable ici
 * (il évolue par la validation agent).
 */
class UpdateVehicleRequest extends FormRequest
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
            'brand' => ['sometimes', 'nullable', 'string', 'max:255'],
            'model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'price_per_day_xof' => ['sometimes', 'integer', 'min:0'],
            'has_driver' => ['sometimes', 'boolean'],
            'caution_xof' => ['sometimes', 'integer', 'min:0'],
            'description' => ['sometimes', 'nullable', 'string'],
            'maps_link' => ['sometimes', 'nullable', 'string', 'max:2048', 'url', new GoogleMapsLink()],
            'insurance_ref' => ['sometimes', 'nullable', 'string', 'max:255'],
            'driver_identity' => ['sometimes', 'nullable', 'string', 'max:255'],
            'life_jackets_count' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'weather_compliant' => ['sometimes', 'nullable', 'boolean'],
            'provider_compliant' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
