<?php

namespace App\Modules\Build\Http\Requests;

use App\Modules\Build\Enums\MilestoneStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation du pilotage d'un jalon de chantier
 * (PATCH /api/v1/construction-milestones/{milestone}) — phase F7.3.e1.
 *
 * Sert les deux gestes de l'écran : faire AVANCER le jalon (statut, date réelle)
 * et le REPLANIFIER (nom, date prévisionnelle). Tous les champs sont optionnels,
 * mais au moins un est exigé — un PATCH vide serait une écriture inutile qui
 * laisserait croire à une modification.
 */
class UpdateMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gerer:chantiers') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', Rule::in(MilestoneStatus::values())],
            'planned_date' => ['sometimes', 'nullable', 'date'],
            'actual_date' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * Refuse un PATCH sans aucun champ exploitable.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                $keys = ['name', 'status', 'planned_date', 'actual_date'];

                if (! collect($keys)->contains(fn (string $key) => $this->has($key))) {
                    $validator->errors()->add(
                        'name',
                        'Indiquez au moins un champ à modifier (nom, statut, date prévue ou date réelle).'
                    );
                }
            },
        ];
    }
}
