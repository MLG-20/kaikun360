<?php

namespace App\Modules\Build\Http\Requests;

use App\Modules\Build\Enums\MilestoneStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de l'ajout d'un jalon à un chantier par un agent
 * (POST /api/v1/construction-requests/{id}/milestones) — phase F7.3.e1.
 *
 * Réservé à la permission `gerer:chantiers` (middleware `can:` sur la route),
 * comme la publication des rapports de suivi.
 */
class StoreMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gerer:chantiers') ?? false;
    }

    /**
     * `position` est facultative : omise, le jalon est ajouté EN FIN de planning
     * (le contrôleur calcule `max(position) + 1`). Un chantier s'allonge par la
     * fin dans la très grande majorité des cas.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(MilestoneStatus::values())],
            'position' => ['nullable', 'integer', 'min:0'],
            'planned_date' => ['nullable', 'date'],
            'actual_date' => ['nullable', 'date'],
        ];
    }
}
