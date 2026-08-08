<?php

namespace App\Modules\Assistant\Tools;

use App\Modules\Assistant\Contracts\AssistantTool;
use App\Modules\Assistant\Support\AssistantContext;

/**
 * La trousse à outils, assemblée POUR CHAQUE APPELANT (phase F10.0).
 *
 * Le registre connaît tous les outils du module ; il n'en expose au cerveau
 * que ceux ouverts au rôle de l'appelant (`AssistantTool::isAvailableFor()`).
 *
 * Trois raisons de filtrer ici plutôt que de tout donner et de trier après :
 *
 *   1. **Sécurité en profondeur.** Un cerveau détourné ne peut pas invoquer un
 *      outil qui ne lui a jamais été présenté. Ce n'est pas la seule barrière
 *      — les policies restent la vraie — mais c'est celle qui coûte le moins.
 *   2. **Justesse.** Un visiteur à qui l'on ne propose pas d'outil back-office
 *      ne reçoit jamais de réponse hors sujet.
 *   3. **Coût.** À partir de F10.4, la liste des outils entre dans l'invite
 *      envoyée au modèle : la borner par rôle, c'est autant de tokens payés en
 *      moins à chaque message.
 *
 * ⚠️ Le filtrage par rôle réduit la surface, il n'autorise rien. L'autorisation
 * réelle a lieu dans l'outil, au contact de la ressource, via les policies
 * existantes.
 */
class ToolRegistry
{
    /**
     * @param  array<int, AssistantTool>  $tools  Tous les outils connus (injectés).
     */
    public function __construct(
        private readonly array $tools = [],
    ) {}

    /**
     * Outils ouverts à cet appelant.
     *
     * @return array<int, AssistantTool>
     */
    public function availableFor(AssistantContext $context): array
    {
        return array_values(array_filter(
            $this->tools,
            fn (AssistantTool $tool) => $tool->isAvailableFor($context),
        ));
    }

    /**
     * Retrouve un outil PAR SON NOM, et seulement s'il est ouvert à l'appelant.
     *
     * C'est le point de passage obligé pour actionner un outil. Renvoyer `null`
     * plutôt que l'outil quand il n'est pas ouvert (au lieu de lever une
     * exception) est délibéré : à partir de F10.4, un modèle pourra inventer un
     * nom d'outil ou en réclamer un qui ne lui appartient pas. Ce cas n'est pas
     * une anomalie serveur — c'est une demande à ignorer poliment.
     */
    public function find(string $name, AssistantContext $context): ?AssistantTool
    {
        foreach ($this->availableFor($context) as $tool) {
            if ($tool->name() === $name) {
                return $tool;
            }
        }

        return null;
    }

    /**
     * Description des outils ouverts, pour l'invite du driver Claude (F10.4)
     * et pour le diagnostic en développement.
     *
     * @return array<int, array{name: string, description: string}>
     */
    public function describeFor(AssistantContext $context): array
    {
        return array_map(
            fn (AssistantTool $tool) => [
                'name' => $tool->name(),
                'description' => $tool->description(),
            ],
            $this->availableFor($context),
        );
    }
}
