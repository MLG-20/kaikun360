<?php

namespace App\Modules\Core\Http\Requests;

use App\Modules\Core\Enums\ProfileType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de l'inscription (POST /api/v1/auth/register).
 *
 * Garantit qu'aucune donnée non valide n'atteint la couche métier :
 * email/téléphone uniques, mot de passe confirmé et suffisamment long,
 * type de profil parmi la liste autorisée.
 */
class RegisterRequest extends FormRequest
{
    /**
     * Endpoint public : tout le monde peut tenter de s'inscrire.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Règles de validation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            // Téléphone facultatif mais, s'il est fourni, il doit être unique
            // (il sert d'identifiant de connexion alternatif).
            'phone' => ['nullable', 'string', 'max:30', 'unique:users,phone'],
            'city' => ['nullable', 'string', 'max:120'],
            // 'confirmed' impose la présence d'un champ password_confirmation identique.
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'profile_type' => ['required', Rule::in(ProfileType::values())],
        ];
    }

    /**
     * Messages d'erreur en français.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire.',
            'email.required' => "L'adresse e-mail est obligatoire.",
            'email.email' => "L'adresse e-mail n'est pas valide.",
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'profile_type.required' => 'Le type de profil est obligatoire.',
            'profile_type.in' => 'Le type de profil sélectionné est invalide.',
        ];
    }
}
