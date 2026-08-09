<?php

namespace App\Modules\Assistant\Brains;

use Anthropic\Client;
use Anthropic\Messages\CacheControlEphemeral;
use Anthropic\Messages\Message;
use Anthropic\Messages\StopReason;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\TextBlockParam;
use Anthropic\Messages\Tool;
use Anthropic\Messages\Tool\InputSchema;
use Anthropic\Messages\ToolChoiceAuto;
use Anthropic\Messages\ToolChoiceNone;
use Anthropic\Messages\ToolUseBlock;
use App\Modules\Assistant\Contracts\AssistantBrain;
use App\Modules\Assistant\Contracts\AssistantTool;
use App\Modules\Assistant\Contracts\ProvidesInputSchema;
use App\Modules\Assistant\Support\AssistantAction;
use App\Modules\Assistant\Support\AssistantContext;
use App\Modules\Assistant\Support\AssistantReply;
use App\Modules\Assistant\Support\ToolResult;
use App\Modules\Assistant\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cerveau CONVERSATIONNEL de l'assistant (phase F10.4).
 *
 * Seconde implémentation d'`AssistantBrain`, activée par `ASSISTANT_DRIVER=claude`.
 * Elle ne remplace pas `RuleBasedBrain` : elle s'ajoute devant lui, et retombe
 * dessus au moindre incident.
 *
 * ── Ce qu'elle apporte, et que le déterministe ne fait pas ──────────────────
 * L'HISTORIQUE. Depuis F10.0, le panneau renvoie les tours précédents, le
 * contrôleur les valide et les plafonne… et le cerveau déterministe les
 * ignore : « et moins cher ? » repart de zéro et finit au support. C'est le
 * gain attendu de cette tranche, et le seul qu'aucun mot-clé ne pouvait
 * produire. Viennent avec : les tournures que personne n'avait prévues, les
 * fautes de frappe, et les paramètres que le modèle sait extraire là où une
 * heuristique se trompait (cf. les défauts d'extraction de F10.3).
 *
 * ── Ce qui NE change pas, et c'est le sujet ─────────────────────────────────
 * Ni le contrat HTTP, ni le panneau Angular, ni les 14 outils, ni les policies.
 * Le modèle ne voit que ce que le registre lui présente et n'agit que par les
 * outils qu'on lui passe. Il n'a AUCUN privilège propre : une injection de
 * prompt réussie lui fait au mieux réclamer un outil qu'il n'a pas — le
 * registre répond `null`, et rien ne se produit.
 *
 * ── Trois garde-fous propres à ce cerveau ───────────────────────────────────
 *   1. **Le repli.** Clé absente, fournisseur en panne, dépassement de délai,
 *      réponse vide, refus du modèle : on retombe sur le déterministe. Un
 *      client ne doit jamais voir une erreur technique dans une bulle.
 *   2. **Le plafond de tours d'outils.** Un modèle qui boucle sur un outil
 *      vide facturerait indéfiniment. Le dernier tour lui retire l'usage des
 *      outils (`ToolChoiceNone`) : il DOIT alors répondre en texte, donc la
 *      conversation se termine toujours, en un nombre d'appels borné.
 *   3. **Le texte vient du modèle, les DONNÉES viennent des outils.** Les
 *      fiches et les boutons affichés sont ceux que les outils ont renvoyés,
 *      recopiés tels quels — jamais ce que le modèle en a redit. Un prix
 *      halluciné ne peut donc pas atteindre l'écran : il n'y a pas de chemin.
 *
 * ⚠️ Ce cerveau ne consulte jamais la base. Il n'a même pas de quoi : il ne
 * reçoit que le registre.
 */
class ClaudeBrain implements AssistantBrain
{
    /**
     * Clés d'une fiche que le modèle n'a pas à voir.
     *
     * `url` en fait partie, et ce n'est pas un détail : l'invite lui interdit
     * d'écrire des adresses, mais la meilleure façon de tenir cette règle est
     * qu'il n'en ait aucune sous les yeux. Les liens continuent de voyager
     * dans les fiches renvoyées au panneau — ils ne passent simplement pas par
     * le modèle. C'est aussi autant de tokens en moins à chaque appel.
     */
    private const HIDDEN_ITEM_KEYS = ['url'];

    public function __construct(
        private readonly Client $client,
        private readonly RuleBasedBrain $fallback,
        private readonly ClaudePrompt $prompt,
    ) {}

    public function reply(
        string $message,
        array $history,
        AssistantContext $context,
        ToolRegistry $tools,
    ): AssistantReply {
        try {
            return $this->converse($message, $history, $context, $tools);
        } catch (Throwable $e) {
            // On dégrade, on n'échoue pas. Le déterministe répond sans clé et
            // sans réseau : c'est exactement le service à rendre quand le
            // fournisseur tombe. L'incident est journalisé côté serveur, pas
            // montré à l'utilisateur.
            Log::warning('Assistant : repli sur le cerveau déterministe.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'role' => $context->role->value,
            ]);

            return $this->fallback->reply($message, $history, $context, $tools);
        }
    }

    // =========================================================================
    // LA CONVERSATION
    // =========================================================================

    /**
     * Un échange complet : appels au modèle et exécutions d'outils entrelacés,
     * jusqu'à une réponse en texte.
     */
    private function converse(
        string $message,
        array $history,
        AssistantContext $context,
        ToolRegistry $tools,
    ): AssistantReply {
        $definitions = $this->toolDefinitions($tools, $context);
        $messages = $this->openingMessages($message, $history);

        $maxToolRounds = max(1, (int) config('assistant.claude.max_tool_rounds', 3));

        /** @var array<int, array<string, mixed>> $items */
        $items = [];
        /** @var array<int, AssistantAction> $actions */
        $actions = [];
        $lastTool = null;

        // Un tour de plus que le nombre de tours d'outils autorisés : le
        // dernier sert à rédiger la réponse, outils coupés.
        for ($round = 1; $round <= $maxToolRounds + 1; $round++) {
            $isLastRound = $round === $maxToolRounds + 1;

            $response = $this->ask($messages, $definitions, $context, forbidTools: $isLastRound);

            if ($response->stopReason !== StopReason::TOOL_USE->value) {
                return $this->compose($this->textOf($response), $items, $actions, $lastTool);
            }

            // Le tour du modèle est recopié dans l'historique de l'échange :
            // sans lui, les identifiants d'appel d'outils n'auraient pas de
            // contrepartie et l'API refuserait la requête suivante.
            $messages[] = ['role' => 'assistant', 'content' => $this->assistantTurn($response)];

            $results = [];

            foreach ($response->content as $block) {
                if (! $block instanceof ToolUseBlock) {
                    continue;
                }

                $tool = $tools->find($block->name, $context);

                if ($tool === null) {
                    // Nom inventé, ou outil qui n'appartient pas à cet appelant.
                    // Ce n'est pas une anomalie serveur : c'est le cas nominal
                    // d'un modèle qui se trompe, ou d'une tentative de sortir
                    // de sa trousse. On le lui dit, et on continue.
                    $results[] = $this->toolResultBlock(
                        $block->id,
                        "Cet outil n'existe pas ou n'est pas disponible pour cette personne.",
                        isError: true,
                    );

                    continue;
                }

                $result = $tool->run($this->cleanInput($block->input), $context);

                $lastTool = $tool->name();
                $items = array_merge($items, $result->items);
                $actions = array_merge($actions, $result->actions);

                $results[] = $this->toolResultBlock($block->id, $this->renderForModel($result));
            }

            $messages[] = ['role' => 'user', 'content' => $results];
        }

        // Inatteignable en théorie : le dernier tour interdit les outils, donc
        // le modèle y répond forcément en texte. Le filet reste, parce qu'une
        // boucle d'agent sans issue est le défaut le plus coûteux qui soit.
        return $this->compose('', $items, $actions, $lastTool);
    }

    /**
     * Un appel au modèle.
     *
     * ⚠️ Aucun paramètre d'échantillonnage (`temperature`, `top_p`) et aucune
     * configuration de réflexion : Haiku 4.5 n'accepte pas `effort`, et un
     * assistant d'orientation n'a rien à « réfléchir » — il choisit un outil et
     * résume ce qu'il a reçu. C'est aussi ce qui garde le coût au niveau annoncé.
     *
     * @param  array<int, mixed>  $messages
     * @param  array<int, Tool>  $definitions
     */
    private function ask(array $messages, array $definitions, AssistantContext $context, bool $forbidTools): Message
    {
        return $this->client->messages->create(
            maxTokens: max(64, (int) config('assistant.claude.max_tokens', 700)),
            messages: $messages,
            model: (string) config('assistant.claude.model', 'claude-haiku-4-5'),
            system: [TextBlockParam::with(
                text: $this->prompt->for($context),
                // Point de cache sur l'invite système : le préfixe rendu est
                // `tools` puis `system`, donc ce marqueur couvre les deux.
                // ⚠️ Il ne PRODUIT pas forcément un cache : le préfixe minimal
                // cacheable de Haiku 4.5 est de 4 096 tokens, que l'invite et
                // les descriptions d'outils n'atteignent pas. Le marqueur ne
                // coûte rien et devient utile tel quel si le modèle est monté
                // en gamme (le minimum tombe à 1 024 sur Sonnet 5).
                cacheControl: CacheControlEphemeral::with(),
            )],
            thinking: null,
            toolChoice: $forbidTools ? ToolChoiceNone::with() : ToolChoiceAuto::with(),
            tools: $definitions === [] ? null : $definitions,
            requestOptions: [
                'timeout' => (float) config('assistant.claude.timeout', 20),
                // Une seule reprise : au-delà, l'utilisateur attend devant une
                // bulle vide alors que le repli déterministe répond tout de suite.
                'maxRetries' => (int) config('assistant.claude.max_retries', 1),
            ],
        );
    }

    // =========================================================================
    // TRADUCTION : NOS OBJETS ↔ CEUX DE L'API
    // =========================================================================

    /**
     * Les outils ouverts à cet appelant, décrits pour le modèle.
     *
     * La trousse est déjà filtrée par le registre (rôle pour les espaces,
     * PERMISSION pour le back-office depuis F10.3) : ce qui n'y est pas ne peut
     * pas être appelé, et ne coûte pas non plus de tokens.
     *
     * @return array<int, Tool>
     */
    private function toolDefinitions(ToolRegistry $tools, AssistantContext $context): array
    {
        return array_map(
            function (AssistantTool $tool): Tool {
                // Onze outils sur quatorze ne prennent rien : leur schéma est un
                // objet sans propriété. Ce n'est pas un oubli — leur réponse
                // dépend entièrement de qui appelle, pas de ce qu'on leur passe.
                $schema = $tool instanceof ProvidesInputSchema
                    ? $tool->inputSchema()
                    : ['properties' => []];

                return Tool::with(
                    inputSchema: InputSchema::with(
                        properties: $schema['properties'] ?? [],
                        required: $schema['required'] ?? [],
                    ),
                    name: $tool->name(),
                    description: $tool->description(),
                );
            },
            $tools->availableFor($context),
        );
    }

    /**
     * L'historique reçu du panneau, converti en tours de conversation.
     *
     * Deux nettoyages qui ne se voient qu'en production :
     *   - un historique commençant par un tour « assistant » fait refuser la
     *     requête (le premier message doit venir de l'utilisateur). Le panneau
     *     ouvre pourtant la conversation par un message d'accueil — donc le cas
     *     est la règle, pas l'exception. On coupe la tête jusqu'au premier
     *     tour utilisateur.
     *   - un tour vide après nettoyage est écarté plutôt que transmis.
     *
     * @param  array<int, array{role: string, text: string}>  $history
     * @return array<int, array<string, mixed>>
     */
    private function openingMessages(string $message, array $history): array
    {
        $turns = [];

        foreach ($history as $turn) {
            $text = trim((string) ($turn['text'] ?? ''));
            $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';

            if ($text === '') {
                continue;
            }

            if ($turns === [] && $role === 'assistant') {
                continue;
            }

            $turns[] = ['role' => $role, 'content' => $text];
        }

        $turns[] = ['role' => 'user', 'content' => $message];

        return $turns;
    }

    /**
     * Le tour du modèle, recopié en blocs simples.
     *
     * On reconstruit des tableaux plutôt que de renvoyer les objets de réponse :
     * l'API distingue les blocs REÇUS des blocs ENVOYÉS (deux familles de
     * classes dans le SDK), et les mélanger casse la sérialisation. Le tableau
     * est le terrain d'entente des deux.
     *
     * @return array<int, array<string, mixed>>
     */
    private function assistantTurn(Message $response): array
    {
        $blocks = [];

        foreach ($response->content as $block) {
            if ($block instanceof TextBlock && trim($block->text) !== '') {
                $blocks[] = ['type' => 'text', 'text' => $block->text];
            }

            if ($block instanceof ToolUseBlock) {
                $blocks[] = [
                    'type' => 'tool_use',
                    'id' => $block->id,
                    'name' => $block->name,
                    'input' => (object) $block->input,
                ];
            }
        }

        return $blocks;
    }

    /**
     * @return array<string, mixed>
     */
    private function toolResultBlock(string $toolUseId, string $content, bool $isError = false): array
    {
        return [
            'type' => 'tool_result',
            'tool_use_id' => $toolUseId,
            'content' => $content,
            'is_error' => $isError,
        ];
    }

    /**
     * Ce que le modèle lit d'un résultat d'outil.
     *
     * Format volontairement pauvre : la phrase de synthèse et les fiches, sans
     * les adresses (cf. HIDDEN_ITEM_KEYS) et sans les boutons. Le modèle n'a pas
     * à connaître les gestes proposés — ils sont posés par la plateforme sous sa
     * réponse, et lui en parler l'inciterait à les décrire de travers.
     */
    private function renderForModel(ToolResult $result): string
    {
        $payload = ['resume' => $result->summary];

        if (! $result->isEmpty()) {
            $payload['fiches'] = array_map(
                fn (array $item) => array_diff_key($item, array_flip(self::HIDDEN_ITEM_KEYS)),
                $result->items,
            );
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ?: $result->summary;
    }

    /**
     * Paramètres reçus du modèle, ramenés à des scalaires.
     *
     * Un modèle peut renvoyer un objet ou un tableau là où l'outil attend une
     * chaîne. Les outils s'en protègent déjà (`(string)` sur une valeur non
     * scalaire lèverait pourtant une erreur en PHP 8), donc on écarte ici tout
     * ce qui n'est pas scalaire plutôt que de le laisser passer.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function cleanInput(array $input): array
    {
        return array_filter($input, fn ($value) => is_scalar($value) || $value === null);
    }

    // =========================================================================
    // LA RÉPONSE RENDUE AU PANNEAU
    // =========================================================================

    /**
     * Assemble la réponse finale.
     *
     * Le texte vient du modèle ; les fiches et les boutons viennent des outils.
     * C'est la séparation qui rend une hallucination inoffensive : le modèle
     * peut se tromper dans sa phrase, il ne peut pas fabriquer une annonce.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, AssistantAction>  $actions
     */
    private function compose(string $text, array $items, array $actions, ?string $lastTool): AssistantReply
    {
        $text = trim($text);

        if ($text === '') {
            // Réponse vide : le modèle a été coupé par le plafond de tokens, a
            // refusé, ou n'a rien produit. Plutôt qu'une bulle blanche, on
            // relaie ce que les outils ont trouvé — et si eux non plus n'ont
            // rien, l'exception fait retomber l'appelant sur le déterministe.
            if ($items === []) {
                throw new \RuntimeException('Le modèle a renvoyé une réponse vide.');
            }

            $text = "Voici ce que j'ai trouvé.";
        }

        $limit = max(1, (int) config('assistant.limits.results_per_tool', 3)) * 2;

        return new AssistantReply(
            text: $text,
            items: array_slice($items, 0, $limit),
            actions: $this->dedupeActions($actions),
            tool: $lastTool,
        );
    }

    /**
     * Écarte les boutons en double.
     *
     * Deux appels au même outil dans un échange (« et à Saly ? ») proposent
     * deux fois « Voir toutes les annonces ». Le panneau les afficherait tels
     * quels, côte à côte.
     *
     * @param  array<int, AssistantAction>  $actions
     * @return array<int, AssistantAction>
     */
    private function dedupeActions(array $actions): array
    {
        $seen = [];
        $kept = [];

        foreach ($actions as $action) {
            $key = $action->kind.'|'.$action->label.'|'.json_encode($action->payload);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $kept[] = $action;
        }

        return array_slice($kept, 0, 3);
    }

    /**
     * Le texte produit par le modèle, blocs concaténés.
     */
    private function textOf(Message $response): string
    {
        $parts = [];

        foreach ($response->content as $block) {
            if ($block instanceof TextBlock) {
                $parts[] = $block->text;
            }
        }

        return trim(implode("\n", $parts));
    }
}
