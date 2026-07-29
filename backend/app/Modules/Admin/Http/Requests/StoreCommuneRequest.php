<?php

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une commune (« ville ») dans le référentiel géographique
 * (F7.2.l — CDC §6 « Paramètres : villes »).
 *
 * Le nom doit être unique AU SEIN du département visé — c'est exactement la
 * contrainte posée en base (`unique(['department_id', 'name'])`) : on la double
 * ici pour renvoyer un 422 lisible plutôt qu'une erreur SQL brute.
 */
class StoreCommuneRequest extends FormRequest
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
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('communes', 'name')->where(
                    fn ($query) => $query->where('department_id', $this->integer('department_id')),
                ),
            ],
            // Nomenclature ANSD : « commune » ou « commune d'arrondissement »
            // (Dakar, Pikine…). Libre car la base ne la contraint pas.
            'type' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Une commune porte déjà ce nom dans ce département.',
        ];
    }
}
