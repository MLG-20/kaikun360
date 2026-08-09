<?php

namespace App\Modules\Assistant\Tools;

use App\Models\Faq;
use App\Modules\Assistant\Contracts\AssistantTool;
use App\Modules\Assistant\Support\AssistantAction;
use App\Modules\Assistant\Support\AssistantContext;
use App\Modules\Assistant\Support\ToolResult;
use Illuminate\Database\Eloquent\Builder;

/**
 * Réponses issues de la foire aux questions (phase F10.0).
 *
 * Intérêt majeur : la FAQ est éditée par le back-office (B13.4). L'assistant
 * puise donc dans un contenu que l'équipe Kaikun maîtrise et met à jour — pas
 * dans la mémoire d'un modèle, qui serait figée et invérifiable.
 *
 * C'est aussi la boucle d'amélioration du produit : les questions restées sans
 * réponse remontent au support, l'équipe complète la FAQ, et l'assistant sait
 * y répondre le lendemain sans qu'on touche à une ligne de code.
 *
 * ── Sécurité ────────────────────────────────────────────────────────────────
 * Le scope `published()` filtre les brouillons : une entrée en cours de
 * rédaction ne fuite pas par l'assistant.
 */
class FaqTool implements AssistantTool
{
    public function name(): string
    {
        return 'consulter_faq';
    }

    public function description(): string
    {
        return 'Cherche une réponse dans la foire aux questions publiée de Kaikun 360 '
            .'(fonctionnement de la plateforme, paiement, vérification des annonces, garanties, '
            .'frais, délais). À utiliser pour toute question sur le SERVICE lui-même, par '
            .'opposition à une recherche d\'annonce. Paramètre : `question` (le texte de la question).';
    }

    /**
     * La FAQ est publique, comme la page `/faqs`.
     */
    public function isAvailableFor(AssistantContext $context): bool
    {
        return true;
    }

    public function run(array $input, AssistantContext $context): ToolResult
    {
        $question = is_string($input['question'] ?? null) ? trim($input['question']) : '';
        $limit = (int) config('assistant.limits.results_per_tool', 3);

        // Recherche par mots significatifs plutôt que sur la phrase entière :
        // « comment est-ce que je paie ? » ne correspond à aucune question
        // stockée telle quelle, alors que « paie » ou « paiement » oui.
        $keywords = $this->keywords($question);

        if ($keywords === []) {
            return ToolResult::empty(
                'Posez-moi votre question sur le fonctionnement de la plateforme et je chercherai la réponse.',
                [AssistantAction::link('Consulter la FAQ', '/faqs')],
            );
        }

        $entries = Faq::query()
            ->published()
            ->where(function (Builder $query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $like = '%'.$keyword.'%';
                    $query->orWhere('question', 'like', $like)
                        ->orWhere('answer', 'like', $like);
                }
            })
            ->limit($limit)
            ->get();

        if ($entries->isEmpty()) {
            return ToolResult::empty(
                "Je n'ai pas trouvé de réponse à cette question dans notre FAQ.",
                [AssistantAction::link('Parcourir la FAQ', '/faqs')],
            );
        }

        return new ToolResult(
            summary: $entries->count() === 1
                ? 'Voici ce que dit notre FAQ :'
                : 'Voici ce que dit notre FAQ à ce sujet :',
            items: $entries->map(fn (Faq $faq) => [
                'question' => $faq->question,
                'reponse' => $faq->answer,
            ])->all(),
            actions: [AssistantAction::link('Voir toute la FAQ', '/faqs')],
        );
    }

    /**
     * Extrait les mots porteurs de sens de la question.
     *
     * On écarte les mots outils (articles, pronoms, auxiliaires) et les mots
     * trop courts : sans cela, « je » ou « la » ferait remonter la FAQ entière.
     *
     * @return array<int, string>
     */
    private function keywords(string $question): array
    {
        $stopWords = [
            'que', 'qui', 'quoi', 'quel', 'quelle', 'quelles', 'quels', 'comment', 'pourquoi',
            'est', 'ce', 'les', 'des', 'une', 'mon', 'ma', 'mes', 'pour', 'avec', 'dans',
            'sur', 'par', 'vous', 'nous', 'je', 'tu', 'il', 'elle', 'the', 'and',
        ];

        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($question), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $keywords = array_filter(
            $words,
            fn (string $word) => mb_strlen($word) >= 4 && ! in_array($word, $stopWords, strict: true),
        );

        // Trois mots suffisent : au-delà, la requête SQL s'allonge sans gagner
        // en pertinence (chaque mot supplémentaire élargit le OU).
        return array_slice(array_values(array_unique($keywords)), 0, 3);
    }
}
