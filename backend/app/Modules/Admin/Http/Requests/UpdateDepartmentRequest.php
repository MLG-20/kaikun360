<?php

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Renommage / rattachement d'un département (F7.2.l).
 *
 * Comme pour les communes, l'unicité est contrôlée dans la région D'ARRIVÉE et
 * en s'ignorant soi-même.
 */
class UpdateDepartmentRequest extends FormRequest
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
        $department = $this->route('department');
        $regionId = $this->integer('region_id') ?: $department->region_id;

        return [
            'region_id' => ['sometimes', 'integer', 'exists:regions,id'],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                Rule::unique('departments', 'name')
                    ->ignore($department->id)
                    ->where(fn ($query) => $query->where('region_id', $regionId)),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Un département porte déjà ce nom dans cette région.',
        ];
    }
}
