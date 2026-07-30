<?php

namespace App\Modules\Build\Http\Requests;

use App\Modules\Build\Enums\ConstructionLot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de l'affectation d'un prestataire BTP à un chantier
 * (POST /api/v1/construction-requests/{id}/assignments) — F7.3.e3.
 *
 * Réservé à la permission `gerer:chantiers` (middleware `can:` sur la route).
 */
class AssignConstructionProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gerer:chantiers') ?? false;
    }

    /**
     * Le **lot** reprend le vocabulaire des devis (F7.3.e2) : on affecte un
     * prestataire À UN CORPS D'ÉTAT, pas au chantier en bloc — c'est ainsi qu'on
     * suit qui doit quoi, et que la commission se calcule par intervention.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider_id' => ['required', 'integer', 'exists:providers,id'],
            'lot' => ['required', Rule::in(ConstructionLot::values())],
            'amount_xof' => ['required', 'integer', 'min:0'],
            'title' => ['nullable', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
