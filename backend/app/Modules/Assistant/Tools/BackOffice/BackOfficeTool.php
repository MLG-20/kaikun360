<?php

namespace App\Modules\Assistant\Tools\BackOffice;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Assistant\Contracts\AssistantTool;
use App\Modules\Assistant\Support\AssistantAction;
use App\Modules\Assistant\Support\AssistantContext;
use App\Modules\Assistant\Support\ToolResult;

/**
 * Socle des outils du BACK-OFFICE (phase F10.3).
 *
 * Les outils de F10.2 montraient à quelqu'un ses propres dossiers ; ceux-ci
 * franchissent une frontière de plus — ils montrent les dossiers **des autres**.
 * C'est le seul endroit du module où l'assistant lit des données qui
 * n'appartiennent pas à celui qui parle. D'où ce socle, qui rassemble les
 * quatre règles qu'aucun outil de cette famille ne doit pouvoir oublier.
 *
 * ── 1. La trousse s'assemble par PERMISSION, pas par rôle ───────────────────
 * **C'est LA règle de la tranche**, et elle diffère de toutes les précédentes.
 * Depuis F7.1.b, le back-office suit le « grant pur par personne » : le rôle
 * `agent_kaikun` n'ouvre que l'accès (`consulter:dashboard-admin`), et chaque
 * dossier qu'un agent a le droit de traiter lui est **délégué individuellement**
 * par le super administrateur. Un outil ouvert au seul rôle contournerait donc
 * cette délégation : le nouvel agent recruté hier, à qui personne n'a encore
 * coché « Gérer les paiements », lirait par la bulle ce que son écran lui
 * refuse. `isAvailableFor()` interroge donc `can()`, exactement comme la route
 * qui sert le même écran.
 *
 * ⚠️ Conséquence voulue et à ne pas « corriger » : **deux agents de la même
 * équipe n'ont pas le même assistant**. C'est le reflet fidèle de leurs droits,
 * pas une incohérence.
 *
 * ⚠️ Le super administrateur n'a AUCUNE permission assignée (il passe par
 * `Gate::before`, cf. `AppServiceProvider::configureAuthorization`). Passer par
 * `can()` — et non par une lecture directe de ses permissions — est ce qui lui
 * ouvre malgré tout la trousse complète. C'est le même piège qu'en F7.4.a, où
 * un rail vide lui avait été servi.
 *
 * ── 2. Lecture seule, sans la moindre exception ─────────────────────────────
 * Aucun outil de cette famille n'écrit, et ce n'est pas une prudence de
 * transition : valider une annonce, confirmer un règlement, rembourser sont des
 * gestes qui engagent Kaikun 360 devant un tiers. Une phrase mal comprise ne
 * doit jamais pouvoir publier un bien ou sortir de l'argent. L'assistant amène
 * l'agent sur le bon écran, avec son dossier ouvert ; c'est l'agent qui tranche.
 *
 * ── 3. La sortie reste fermée, et davantage encore qu'ailleurs ──────────────
 * Les fiches ne portent que des champs d'écran (compteur, référence, statut,
 * montant). Jamais un objet complet : un dossier de back-office contient des
 * motifs de refus, des notes de sanction, des preuves de paiement et des
 * coordonnées de tiers — rien de tout cela n'a à traverser une bulle de
 * discussion, fût-elle celle d'un administrateur.
 *
 * ── 4. Ces outils suivent la personne, pas l'écran ──────────────────────────
 * Le panneau est monté dans le shell du back-office (F10.3), mais un agent qui
 * discute depuis le site public garde sa trousse : le contexte est construit à
 * partir du JETON, pas de la page d'où part le message. C'est cohérent — ses
 * droits ne changent pas selon l'onglet ouvert — et c'est ce qui permet à un
 * agent en déplacement de demander « qu'est-ce qui attend une validation ? »
 * sans rouvrir le poste de commandement.
 */
abstract class BackOfficeTool implements AssistantTool
{
    /**
     * Racine des écrans du poste de commandement.
     *
     * ⚠️ Écrite ici une seule fois, et jamais recopiée dans les outils : c'est
     * le pendant de `SpaceLink` pour les espaces connectés (dont le défaut
     * corrigé en F10.1 venait justement d'un chemin en dur).
     */
    protected const RACINE = '/back-office';

    /**
     * Permission fine exigée pour que l'outil soit seulement PROPOSÉ.
     *
     * La même que la route back-office qui sert l'écran correspondant : on ne
     * réinvente pas une règle d'accès, on recopie celle qui fait déjà autorité.
     */
    abstract protected function permission(): AdminPermission;

    /**
     * Équipe back-office ET permission fine.
     *
     * Les deux conditions, et pas une seule. `isStaff()` seul laisserait passer
     * l'agent sans délégation (règle 1 ci-dessus) ; `can()` seul suffirait en
     * pratique — aucun client ne porte ces permissions — mais reposerait sur
     * cette seule coïncidence. Une porte de back-office se ferme deux fois.
     */
    public function isAvailableFor(AssistantContext $context): bool
    {
        if (! $context->isAuthenticated() || ! $context->isStaff()) {
            return false;
        }

        return $context->user->can($this->permission()->value);
    }

    /**
     * Nombre de fiches renvoyées.
     *
     * L'assistant donne le pouls, pas la file : trois lignes et un lien vers
     * l'écran qui sait filtrer, trier et paginer.
     */
    protected function limit(): int
    {
        return (int) config('assistant.limits.results_per_tool', 3);
    }

    /**
     * Adresse d'un écran du back-office.
     */
    protected function boUrl(string $path = ''): string
    {
        return $path === '' ? self::RACINE : self::RACINE.'/'.ltrim($path, '/');
    }

    /**
     * Montant lisible en francs CFA, ou `null` s'il n'y en a pas.
     *
     * Espace fine insécable comme séparateur, à l'identique du frontend
     * (`shared/format/fcfa.ts`) et des outils de F10.2 : deux écritures du même
     * montant sur la même page donneraient l'impression de deux sources.
     */
    protected function money(?int $amount): ?string
    {
        if ($amount === null) {
            return null;
        }

        return number_format($amount, 0, ',', "\u{202f}")."\u{202f}F";
    }

    /**
     * Fiche « compteur » : un intitulé, un nombre, l'écran qui le traite.
     *
     * ⚠️ Le nombre part en `statut` (et non en `detail`) pour une raison
     * d'affichage : le panneau rend `statut` en pastille, ce qui donne au chiffre
     * la lisibilité d'un badge. C'est le seul endroit du module où un champ est
     * choisi pour son rendu — assumé, et la raison est écrite ici pour qu'on ne
     * le « range » pas ailleurs par souci de cohérence apparente.
     *
     * @return array<string, mixed>
     */
    protected function counter(string $label, int $count, string $url, ?string $detail = null): array
    {
        return array_filter([
            'titre' => $label,
            'statut' => (string) $count,
            'detail' => $detail,
            'url' => $url,
        ], fn ($value) => $value !== null);
    }

    /**
     * Résultat vide, avec la porte de sortie vers l'écran concerné.
     *
     * Un « rien à signaler » est ici une bonne nouvelle — mais il doit rester
     * vérifiable : sans lien, l'agent ne sait pas si la file est vide ou si
     * l'assistant a mal compris sa question.
     */
    protected function nothing(string $summary, string $label, string $url): ToolResult
    {
        return ToolResult::empty($summary, [
            AssistantAction::link($label, $url),
        ]);
    }
}
