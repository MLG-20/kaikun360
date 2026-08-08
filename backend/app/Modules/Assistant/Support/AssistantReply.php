<?php

namespace App\Modules\Assistant\Support;

/**
 * Réponse de l'assistant renvoyée au frontend (phase F10.0).
 *
 * C'est le CONTRAT que le panneau Angular consomme. Il est délibérément
 * indépendant du cerveau qui l'a produit : le driver déterministe (F10.0) et
 * le driver Claude (F10.4) remplissent exactement la même structure.
 *
 * D'où l'intérêt : basculer de l'un à l'autre ne change pas une ligne côté
 * Angular, et un repli du second vers le premier (clés indisponibles, panne du
 * fournisseur) reste invisible pour l'utilisateur.
 */
final class AssistantReply
{
    /**
     * @param  string                            $text     Message affiché dans la bulle.
     * @param  array<int, array<string, mixed>>  $items    Fiches de résultats (peut être vide).
     * @param  array<int, AssistantAction>       $actions  Boutons proposés.
     * @param  string|null                       $tool     Outil ayant produit la réponse (traçabilité).
     */
    public function __construct(
        public readonly string $text,
        public readonly array $items = [],
        public readonly array $actions = [],
        public readonly ?string $tool = null,
    ) {}

    /**
     * Compose une réponse à partir du résultat d'un outil.
     */
    public static function fromTool(string $tool, ToolResult $result): self
    {
        return new self($result->summary, $result->items, $result->actions, $tool);
    }

    /**
     * Réponse sans outil : l'assistant n'a pas compris la demande.
     *
     * Ce cas n'est PAS un échec à masquer. Le dire franchement et proposer une
     * sortie (support, contact) vaut mieux qu'inventer une réponse — c'est
     * précisément ce qui fait perdre la confiance sur un site marchand.
     */
    public static function fallback(string $text, array $actions = []): self
    {
        return new self($text, [], $actions, null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'items' => $this->items,
            'actions' => array_map(fn (AssistantAction $a) => $a->toArray(), $this->actions),
            'tool' => $this->tool,
        ];
    }
}
