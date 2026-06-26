<?php

namespace App\Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la mise à jour du profil (PATCH /api/v1/users/me).
 *
 * Mise à jour PARTIELLE : on utilise "sometimes" pour ne valider que les
 * champs réellement envoyés. L'e-mail et le téléphone ne sont volontairement
 * PAS modifiables ici : leur changement nécessitera une re-vérification
 * dédiée (à traiter ultérieurement).
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // L'utilisateur met à jour son propre profil (endpoint /me).
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'preferences' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom ne peut pas être vide.',
            'preferences.array' => 'Les préférences doivent être un objet clé/valeur.',
        ];
    }
}
