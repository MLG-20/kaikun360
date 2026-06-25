<?php

namespace App\Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la connexion (POST /api/v1/auth/login).
 *
 * Le champ `login` accepte indifféremment un e-mail OU un numéro de téléphone
 * (cf. cahier des charges B1) ; le contrôleur déterminera lequel.
 */
class LoginRequest extends FormRequest
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
            'login' => ['required', 'string'],      // e-mail ou téléphone
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login.required' => "L'identifiant (e-mail ou téléphone) est obligatoire.",
            'password.required' => 'Le mot de passe est obligatoire.',
        ];
    }
}
