<?php

namespace App\Modules\Pro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la proposition d'une nouvelle catégorie de service
 * (POST /api/v1/providers/categories) — cf. `ProviderProfileController::storeCategory`.
 */
class StoreProviderCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:100'],
        ];
    }
}
