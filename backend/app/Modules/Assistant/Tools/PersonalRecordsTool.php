<?php

namespace App\Modules\Assistant\Tools;

use App\Modules\Assistant\Contracts\AssistantTool;
use App\Modules\Assistant\Support\AssistantAction;
use App\Modules\Assistant\Support\AssistantContext;
use App\Modules\Assistant\Support\ToolResult;
use App\Modules\Core\Enums\UserRole;
use App\Support\Mail\SpaceLink;

/**
 * Socle des outils qui montrent à quelqu'un SES PROPRES dossiers (phase F10.2).
 *
 * Les trois outils de F10.0 travaillaient sur des données publiques : le
 * catalogue, la FAQ. Ceux-ci franchissent une frontière — ils lisent des données
 * personnelles. D'où ce socle, qui rassemble en un seul endroit les règles
 * qu'aucun outil de cette famille ne doit pouvoir oublier.
 *
 * ── 1. Le cloisonnement ne se redémontre pas, il se recopie ─────────────────
 * Chaque outil scope sa requête **exactement comme le contrôleur HTTP** qui sert
 * le même écran (`where('user_id', …)`, `where('owner_id', …)`,
 * `whereHas('provider', …)`). Ce n'est pas de la paresse : réécrire une
 * condition d'appartenance « à peu près pareil » est la façon la plus banale de
 * créer une fuite. La règle du module reste entière — l'assistant n'a **aucun
 * privilège propre**, il ne voit que ce que l'appelant verrait en ouvrant son
 * espace.
 *
 * ── 2. Personne d'anonyme n'entre ici ───────────────────────────────────────
 * `isAvailableFor()` exige une session **et** le rôle attendu. Un visiteur ne se
 * voit donc jamais proposer ces outils, et le registre ne les lui présente même
 * pas (voir ToolRegistry) : un cerveau détourné ne peut pas invoquer un outil
 * qu'il n'a jamais reçu.
 *
 * ── 3. Lecture seule, sans exception ────────────────────────────────────────
 * Aucun outil de cette famille n'écrit. Ce qui se décide — payer, annuler,
 * accepter une mission — reste un geste que l'utilisateur pose lui-même sur
 * l'écran concerné, avec ses confirmations. L'assistant y conduit, il ne le fait
 * pas à sa place.
 *
 * ── 4. La sortie reste fermée ───────────────────────────────────────────────
 * Les fiches renvoyées ne portent que des champs d'affichage (référence, statut,
 * date, montant). Jamais l'objet complet : un dossier personnel contient des
 * commentaires internes, des identifiants techniques et parfois les coordonnées
 * d'un tiers, dont rien n'a à traverser une bulle de discussion.
 */
abstract class PersonalRecordsTool implements AssistantTool
{
    /**
     * Rôles auxquels cet outil s'adresse.
     *
     * @return array<int, UserRole>
     */
    abstract protected function roles(): array;

    /**
     * Session obligatoire ET rôle attendu.
     *
     * ⚠️ L'ordre compte : sans `isAuthenticated()`, `$context->role` vaudrait
     * `VISITEUR` et la comparaison suffirait — mais un outil qui interroge
     * `$context->user->id` sur un utilisateur nul lèverait une erreur 500 au
     * lieu de se taire. On refuse donc explicitement, en amont.
     */
    public function isAvailableFor(AssistantContext $context): bool
    {
        if (! $context->isAuthenticated()) {
            return false;
        }

        return in_array($context->role, $this->roles(), strict: true);
    }

    /**
     * Nombre de fiches renvoyées, commun à tous les outils.
     *
     * L'assistant donne un aperçu — « où en sont mes dossiers ? » — et renvoie à
     * l'écran pour le reste. Dérouler quinze réservations dans une bulle ne
     * remplace pas une liste filtrable, ça la remplace mal.
     */
    protected function limit(): int
    {
        return (int) config('assistant.limits.results_per_tool', 3);
    }

    /**
     * Adresse d'un écran DANS L'ESPACE de l'appelant.
     *
     * ⚠️ Passage obligé. Le site a quatre espaces connectés et `/mon-espace` est
     * gardé par le rôle `client` : un chemin écrit en dur envoie un propriétaire
     * ou un prestataire sur un refus d'accès (le défaut corrigé en F10.1).
     */
    protected function spaceUrl(AssistantContext $context, string $path = ''): string
    {
        return SpaceLink::to($context->user, $path);
    }

    /**
     * Montant lisible en francs CFA, ou `null` s'il n'y en a pas.
     *
     * Espace fine insécable comme séparateur de milliers, à l'identique du
     * frontend (`shared/format/fcfa.ts`) : deux écritures différentes du même
     * prix sur la même page donneraient l'impression de deux sources.
     */
    protected function money(?int $amount): ?string
    {
        if ($amount === null) {
            return null;
        }

        return number_format($amount, 0, ',', "\u{202f}")."\u{202f}F";
    }

    /**
     * Résultat vide, avec la porte de sortie vers l'écran concerné.
     *
     * Un « vous n'avez rien » sans issue laisse la personne devant un mur :
     * elle ne sait pas si l'assistant s'est trompé ou si son dossier a disparu.
     */
    protected function nothing(string $summary, string $label, string $url): ToolResult
    {
        return ToolResult::empty($summary, [
            AssistantAction::link($label, $url),
        ]);
    }
}
