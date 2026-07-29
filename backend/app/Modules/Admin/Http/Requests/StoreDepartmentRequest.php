<?php

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un département dans le référentiel géographique (F7.2.l).
 *
 * Même logique d'unicité que les communes, un cran plus haut : un nom de
 * département est unique au sein de sa région.
 */
class StoreDepartmentRequest extends FormRequest
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
            'region_id' => ['required', 'integer', 'exists:regions,id'],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('departments', 'name')->where(
                    fn ($query) => $query->where('region_id', $this->integer('region_id')),
                ),
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
