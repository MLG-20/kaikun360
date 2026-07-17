<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de l'ouverture d'une nouvelle conversation (POST /api/v1/messages),
 * messagerie F3.7.
 *
 * On exige un destinataire (`recipient_id`, un compte existant et différent de
 * soi) et un premier message. Le sujet est facultatif. Le contexte polymorphe
 * (`context_type` / `context_id`) est prévu pour rattacher le fil à une demande
 * ou une réservation, mais reste optionnel à ce stade.
 */
class StartConversationRequest extends FormRequest
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
            'recipient_id' => [
                'required',
                'integer',
                'exists:users,id',
                // On ne peut pas ouvrir une conversation avec soi-même.
                Rule::notIn([$this->user()?->id]),
            ],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipient_id.not_in' => 'Vous ne pouvez pas démarrer une conversation avec vous-même.',
        ];
    }
}
