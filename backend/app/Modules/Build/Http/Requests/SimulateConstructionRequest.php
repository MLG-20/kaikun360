<?php

namespace App\Modules\Build\Http\Requests;

use App\Modules\Build\Enums\ConstructionObjective;
use App\Modules\Build\Enums\ConstructionZone;
use App\Modules\Build\Enums\FinishLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation d'une simulation de budget (POST /api/v1/construction-requests/simulate).
 *
 * Ne persiste rien et n'expose aucune donnée personnelle : sert uniquement à
 * alimenter le simulateur (B5.4, enrichi). L'endpoint est PUBLIC — la page
 * Construction du site est accessible sans compte — d'où `authorize() = true`.
 *
 * Seuls `objective`, `surface_m2` et `finish_level` sont requis ; les autres
 * paramètres (niveaux, zone, coût du terrain) ont des valeurs par défaut sûres.
 */
class SimulateConstructionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'objective' => ['required', Rule::in(ConstructionObjective::values())],
            'surface_m2' => ['required', 'integer', 'min:1', 'max:100000'],
            'finish_level' => ['required', Rule::in(FinishLevel::values())],
            'levels' => ['nullable', 'integer', 'min:1', 'max:10'],
            'zone' => ['nullable', Rule::in(ConstructionZone::values())],
            'land_cost_xof' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
