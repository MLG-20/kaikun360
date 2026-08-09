<?php

namespace App\Modules\Assistant\Support;

/**
 * Ce qu'un outil de l'assistant renvoie (phase F10.0).
 *
 * Format volontairement pauvre et FERMÉ. Un outil ne renvoie jamais un modèle
 * Eloquent ni une ressource complète : il renvoie des fiches déjà réduites aux
 * champs destinés à l'affichage.
 *
 * C'est la parade à l'exfiltration par la réponse : même si le cerveau était
 * détourné (injection de prompt), il ne pourrait recracher que ce qui a
 * traversé cet objet — jamais un identifiant technique, un jeton, une adresse
 * e-mail ou un champ interne qui n'a rien à faire dans une bulle de discussion.
 */
final class ToolResult
{
    /**
     * @param  string  $summary  Phrase de synthèse, en français.
     * @param  array<int, array<string, mixed>>  $items  Fiches à afficher (déjà filtrées).
     * @param  array<int, AssistantAction>  $actions  Gestes proposés.
     */
    public function __construct(
        public readonly string $summary,
        public readonly array $items = [],
        public readonly array $actions = [],
    ) {}

    /**
     * Résultat vide (aucune correspondance), avec une porte de sortie.
     *
     * Un assistant qui répond « je n'ai rien trouvé » et s'arrête est un cul-de-sac :
     * on renvoie toujours au moins une action pour que l'utilisateur avance.
     */
    public static function empty(string $summary, array $actions = []): self
    {
        return new self($summary, [], $actions);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
