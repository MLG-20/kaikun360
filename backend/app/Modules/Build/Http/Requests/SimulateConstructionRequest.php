<?php

namespace App\Modules\Build\Http\Requests;

use App\Modules\Build\Enums\ConstructionObjective;
use App\Modules\Build\Enums\FinishLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation d'une simulation de budget (POST /api/v1/construction-requests/simulate).
 *
 * Ne persiste rien : sert uniquement à alimenter le simulateur (B5.4).
 */
class SimulateConstructionRequest extends FormRequest
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
            'objective' => ['required', Rule::in(ConstructionObjective::values())],
            'surface_m2' => ['required', 'integer', 'min:1'],
            'finish_level' => ['required', Rule::in(FinishLevel::values())],
        ];
    }
}
