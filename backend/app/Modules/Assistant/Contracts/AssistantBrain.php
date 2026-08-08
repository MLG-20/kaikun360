<?php

namespace App\Modules\Assistant\Contracts;

use App\Modules\Assistant\Support\AssistantContext;
use App\Modules\Assistant\Support\AssistantReply;
use App\Modules\Assistant\Tools\ToolRegistry;

/**
 * Le « cerveau » de l'assistant (phase F10.0).
 *
 * Unique point de variation du module. Deux implémentations sont prévues :
 *
 *   - `RuleBasedBrain` (F10.0) — compréhension déterministe par mots-clés.
 *     Aucune clé API, aucun coût, aucun appel réseau. Sert de driver par défaut
 *     ET de repli si le second devient indisponible.
 *   - `ClaudeBrain` (F10.4) — modèle de langage utilisant les mêmes outils.
 *
 * Les deux reçoivent la MÊME trousse à outils et produisent la MÊME structure
 * de réponse. C'est ce qui permet de basculer par simple variable
 * d'environnement, sans toucher au frontend ni aux tests d'intégration.
 *
 * ⚠️ Un cerveau ne consulte jamais la base directement : il ne peut agir que
 * par les outils qu'on lui passe. C'est ce qui borne ce qu'il peut voir et
 * faire, indépendamment de sa nature.
 */
interface AssistantBrain
{
    /**
     * Produit une réponse au message de l'utilisateur.
     *
     * @param  string  $message  Message entrant, déjà validé et plafonné.
     * @param  array<int, array{role: string, text: string}>  $history  Tours précédents, déjà bornés.
     */
    public function reply(
        string $message,
        array $history,
        AssistantContext $context,
        ToolRegistry $tools,
    ): AssistantReply;
}
