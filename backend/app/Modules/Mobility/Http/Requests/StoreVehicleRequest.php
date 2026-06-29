<?php

namespace App\Modules\Mobility\Http\Requests;

use App\Modules\Mobility\Enums\VehicleType;
use App\Modules\Mobility\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation du dépôt d'un véhicule (POST /api/v1/vehicles).
 *
 * Autorisation via la policy `create` (prestataire vérifié). Les champs de
 * conformité sont facultatifs ici mais bloqueront la VALIDATION s'ils manquent
 * (cf. VehicleComplianceChecker).
 */
class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Vehicle::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(VehicleType::values())],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1'],
            'price_per_day_xof' => ['required', 'integer', 'min:0'],
            'has_driver' => ['boolean'],
            'caution_xof' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],

            // Conformité motorisé.
            'insurance_ref' => ['nullable', 'string', 'max:255'],
            'driver_identity' => ['nullable', 'string', 'max:255'],
            // Conformité pirogue.
            'life_jackets_count' => ['nullable', 'integer', 'min:0'],
            'weather_compliant' => ['nullable', 'boolean'],
            'provider_compliant' => ['nullable', 'boolean'],
        ];
    }
}
