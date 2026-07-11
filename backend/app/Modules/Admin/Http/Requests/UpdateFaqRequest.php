<?php

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise à jour partielle d'une entrée de FAQ (B13.4).
 */
class UpdateFaqRequest extends FormRequest
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
            'question' => ['sometimes', 'required', 'string', 'max:255'],
            'answer' => ['sometimes', 'required', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:100'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'is_published' => ['boolean'],
        ];
    }
}
