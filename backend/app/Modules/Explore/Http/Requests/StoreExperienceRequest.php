<?php

namespace App\Modules\Explore\Http\Requests;

use App\Modules\Explore\Models\TourismExperience;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la publication d'une expérience (POST /api/v1/experiences).
 *
 * L'autorisation passe par la policy `create` (prestataire vérifié uniquement).
 */
class StoreExperienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TourismExperience::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'price_xof' => ['required', 'integer', 'min:0'],
            'capacity' => ['required', 'integer', 'min:1'],
            // Inclusions structurées (ex. {"restauration": true, "guide": true}).
            'inclusions' => ['nullable', 'array'],
        ];
    }
}
