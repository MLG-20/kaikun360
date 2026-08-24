<?php

namespace App\Modules\Pro\Http\Requests;

use App\Modules\Pro\Rules\AssignableProviderCategory;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de l'édition du profil prestataire (PUT /api/v1/providers/mine).
 *
 * Reprend les mêmes champs que l'inscription (hors certifications, gérées à
 * part), tous requis : le prestataire met à jour la description de SON service.
 * L'autorisation réelle (posséder un profil) est faite dans le contrôleur
 * (`providerFor` → 404 sinon).
 */
class UpdateProviderProfileRequest extends FormRequest
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
            'business_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', new AssignableProviderCategory($this->user())],
            'bio' => ['nullable', 'string'],
        ];
    }
}
