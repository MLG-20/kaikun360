<?php

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour partielle d'une page de contenu (B13.4).
 */
class UpdatePageRequest extends FormRequest
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
            'slug' => [
                'sometimes', 'required', 'string', 'max:150', 'regex:/^[a-z0-9-]+$/',
                // Unicité en ignorant la page courante (résolue par slug).
                Rule::unique('pages', 'slug')->ignore($this->route('page')?->id),
            ],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string'],
            'is_published' => ['boolean'],
        ];
    }
}
