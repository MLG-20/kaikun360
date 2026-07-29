<?php

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Renommage / reclassement d'une commune (F7.2.l).
 *
 * Mise à jour PARTIELLE (`sometimes`) : le back-office n'envoie que les champs
 * réellement modifiés. L'unicité du nom est vérifiée dans le département
 * D'ARRIVÉE (celui du corps s'il est fourni, sinon celui de la commune), en
 * s'ignorant elle-même — sans quoi un simple changement de `type` échouerait.
 */
class UpdateCommuneRequest extends FormRequest
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
        $commune = $this->route('commune');
        $departmentId = $this->integer('department_id') ?: $commune->department_id;

        return [
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                Rule::unique('communes', 'name')
                    ->ignore($commune->id)
                    ->where(fn ($query) => $query->where('department_id', $departmentId)),
            ],
            'type' => ['sometimes', 'nullable', 'string', 'max:60'],
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
