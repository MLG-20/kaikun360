<?php

namespace App\Modules\Pro\Http\Requests;

use App\Modules\Pro\Rules\AssignableProviderCategory;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de l'inscription prestataire (POST /api/v1/providers).
 *
 * Tout utilisateur authentifié peut créer SON profil prestataire (un seul, la
 * relation étant 1–1). Les certifications sont facultatives à l'inscription.
 */
class RegisterProviderRequest extends FormRequest
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
            'certifications' => ['nullable', 'array'],
            'certifications.*.name' => ['required_with:certifications', 'string', 'max:255'],
            'certifications.*.issuer' => ['nullable', 'string', 'max:255'],
        ];
    }
}
