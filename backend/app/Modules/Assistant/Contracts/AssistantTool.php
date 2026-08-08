<?php

namespace App\Modules\Assistant\Contracts;

use App\Modules\Assistant\Support\AssistantContext;
use App\Modules\Assistant\Support\ToolResult;

/**
 * Un outil que l'assistant peut actionner (phase F10.0).
 *
 * RÈGLE FONDAMENTALE DU MODULE : un outil n'accède jamais à la base « à la
 * main » pour contourner une vérification. Il appelle les mêmes portes que les
 * contrôleurs HTTP — scopes publics (`published()`, `bookable()`) pour le
 * catalogue, policies pour tout ce qui est nominatif — et il s'exécute AU NOM
 * de l'utilisateur porté par le contexte.
 *
 * Conséquence voulue : l'assistant n'a aucun privilège propre. Tout ce qui a
 * été sécurisé de F0 à F9 continue de faire autorité, et une injection de
 * prompt réussie ne débloque rien — le modèle peut demander ce qu'il veut, la
 * policy répond non.
 *
 * `description()` n'est pas de la décoration : c'est le texte que le driver
 * Claude (F10.4) donnera au modèle pour qu'il choisisse ses outils. Il doit
 * dire QUAND utiliser l'outil, pas seulement ce qu'il fait.
 */
interface AssistantTool
{
    /**
     * Identifiant technique, en français, en minuscules (ex. `rechercher_catalogue`).
     */
    public function name(): string;

    /**
     * À quoi sert l'outil et quand y recourir.
     */
    public function description(): string;

    /**
     * Cet outil est-il ouvert à cet appelant ?
     *
     * Premier filtre, grossier : il compose la trousse selon le rôle. Il ne
     * remplace pas les policies — c'est une réduction de surface, pas une
     * autorisation. Un outil dont `isAvailableFor()` renverrait true à tort
     * reste bloqué plus bas par la policy de la ressource touchée.
     */
    public function isAvailableFor(AssistantContext $context): bool;

    /**
     * Exécute l'outil.
     *
     * @param  array<string, mixed>  $input  Paramètres extraits du message.
     */
    public function run(array $input, AssistantContext $context): ToolResult;
}
