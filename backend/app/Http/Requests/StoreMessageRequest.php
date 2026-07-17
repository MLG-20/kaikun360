<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de l'envoi d'un message dans une conversation existante
 * (POST /api/v1/messages/{conversation}/messages), messagerie F3.7.
 *
 * L'appartenance de l'utilisateur à la conversation est vérifiée dans le
 * contrôleur (accès scopé à ses propres fils) ; ici on ne valide que le corps.
 */
class StoreMessageRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
