<?php

namespace App\Modules\Assistant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation d'un message envoyé à l'assistant (phase F10.0).
 *
 * Première ligne de défense de l'endpoint, et elle porte plus qu'il n'y paraît.
 * L'historique est fourni PAR LE CLIENT (l'assistant est sans état côté
 * serveur) : sans plafond, n'importe qui pourrait renvoyer dix mille tours à
 * chaque appel et faire gonfler le traitement — puis, dès le driver Claude
 * (F10.4), la facture. Les bornes viennent de `config/assistant.php` pour être
 * ajustables sans redéploiement.
 */
class AssistantMessageRequest extends FormRequest
{
    /**
     * L'endpoint est ouvert aux visiteurs comme aux comptes connectés :
     * l'autorisation fine se joue plus bas, au niveau des outils et des
     * policies, jamais ici.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxLength = (int) config('assistant.limits.message_length', 500);
        $maxTurns = (int) config('assistant.limits.history_turns', 10);

        return [
            'message' => ['required', 'string', 'min:1', 'max:'.$maxLength],

            // Historique renvoyé par le panneau pour donner du contexte.
            'history' => ['sometimes', 'array', 'max:'.$maxTurns],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.text' => ['required_with:history', 'string', 'max:'.$maxLength],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Veuillez saisir votre question.',
            'message.max' => 'Votre message est trop long : reformulez-le en :max caractères maximum.',
            'history.max' => 'Historique de conversation trop long.',
        ];
    }

    /**
     * Historique nettoyé, prêt pour le cerveau.
     *
     * On ne fait pas confiance à l'ordre ni à la forme reçus : on ne garde que
     * les entrées bien formées et on retient les N DERNIERS tours (les plus
     * pertinents), quoi qu'en dise le client.
     *
     * @return array<int, array{role: string, text: string}>
     */
    public function cleanHistory(): array
    {
        $maxTurns = (int) config('assistant.limits.history_turns', 10);

        $history = array_values(array_filter(
            (array) $this->validated('history', []),
            fn ($turn) => is_array($turn) && isset($turn['role'], $turn['text']),
        ));

        return array_slice($history, -$maxTurns);
    }
}
