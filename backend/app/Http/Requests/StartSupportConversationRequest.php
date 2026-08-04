<?php

namespace App\Http\Requests;

use App\Support\Messaging\ConversationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de l'ouverture d'un fil AVEC LE SUPPORT (POST /api/v1/messages/support),
 * messagerie F8.12.
 *
 * Différence essentielle avec `StartConversationRequest` : **il n'y a pas de
 * destinataire à fournir**. C'est tout le principe du support pivot — le client
 * ne choisit pas son interlocuteur, le serveur le lui assigne
 * (`SupportAssignmentService`). Laisser le client désigner un compte reviendrait
 * à rouvrir la porte que cette architecture ferme : écrire directement au
 * propriétaire ou au prestataire, hors de toute supervision.
 *
 * Le contexte (`context_type` = un slug public, `context_id`) est facultatif ;
 * s'il est fourni, seul un slug de la liste blanche est accepté et l'accès au
 * dossier est revérifié à la résolution (cf. `ConversationContext`).
 */
class StartSupportConversationRequest extends FormRequest
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
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],

            // Dossier concerné : les deux champs vont ensemble ou pas du tout.
            'context_type' => ['nullable', 'string', Rule::in(ConversationContext::slugs()), 'required_with:context_id'],
            'context_id' => ['nullable', 'integer', 'required_with:context_type'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Écrivez votre message avant de l’envoyer.',
            'context_type.in' => 'Ce type de dossier n’est pas reconnu.',
        ];
    }
}
