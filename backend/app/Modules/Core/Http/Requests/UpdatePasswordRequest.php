<?php

namespace App\Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation du changement de mot de passe (PATCH /api/v1/users/me/password, F3.2b).
 *
 * La règle `current_password` vérifie que `current_password` correspond bien au
 * mot de passe ACTUEL de l'utilisateur connecté : une session ouverte ne suffit
 * pas à le remplacer. Le nouveau mot de passe suit les mêmes règles qu'à
 * l'inscription (min. 8 caractères, confirmé).
 */
class UpdatePasswordRequest extends FormRequest
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
            // `current_password:sanctum` : vérifie le mot de passe actuel via le
            // guard d'API (l'utilisateur est authentifié par jeton Sanctum).
            'current_password' => ['required', 'string', 'current_password:sanctum'],
            // 'confirmed' impose un champ password_confirmation identique.
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Le mot de passe actuel est obligatoire.',
            'current_password.current_password' => 'Le mot de passe actuel est incorrect.',
            'password.required' => 'Le nouveau mot de passe est obligatoire.',
            'password.min' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'password.different' => "Le nouveau mot de passe doit être différent de l'actuel.",
        ];
    }
}
