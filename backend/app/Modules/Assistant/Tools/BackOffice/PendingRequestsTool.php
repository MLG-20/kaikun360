<?php

namespace App\Modules\Assistant\Tools\BackOffice;

use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Assistant\Support\AssistantAction;
use App\Modules\Assistant\Support\AssistantContext;
use App\Modules\Assistant\Support\ToolResult;

/**
 * « Qu'est-ce qui m'attend dans la file des demandes ? » (phase F10.3).
 *
 * Le CDC §7 confie explicitement le « traitement demandes » à l'agent Kaikun.
 * L'écran existe depuis F8.9 — il a d'ailleurs comblé un trou plus ancien : de
 * B11.2 à F8.9, l'alerte « Nouvelle demande à traiter » invitait à ouvrir une
 * file que rien ne permettait de lister. Cet outil raccourcit le dernier
 * segment : savoir ce qui attend sans ouvrir l'écran.
 *
 * ── L'ordre est celui de l'écran, pas celui de la base ──────────────────────
 * ⚠️ Le tri est **recopié du contrôleur** : urgences d'abord, puis les plus
 * anciennes. Ce n'est pas de la présentation, c'est la règle métier de la file —
 * dans un guichet, le dossier qui attend depuis le plus longtemps est celui qui
 * coûte le plus cher. Trier ici « par date de création décroissante », réflexe
 * naturel, montrerait les trois derniers arrivés et cacherait précisément ceux
 * qu'il fallait voir.
 *
 * ⚠️ Le `CASE` SQL plutôt que `FIELD()` de MySQL vient aussi du contrôleur : la
 * suite de tests doit pouvoir tourner sur SQLite.
 *
 * ── Lecture seule ──────────────────────────────────────────────────────────
 * Faire avancer une demande, c'est franchir un cran d'une machine à états
 * stricte (`RequestStatus::allowedNext()`, sans retour en arrière possible).
 * L'assistant ne touche pas à ça : il ouvre la file, l'agent avance.
 */
class PendingRequestsTool extends BackOfficeTool
{
    public function name(): string
    {
        return 'demandes_a_traiter';
    }

    public function description(): string
    {
        return 'Liste les demandes clients encore ouvertes dans la file de traitement de l\'équipe, '
            .'les urgentes puis les plus anciennes : référence, service concerné, ville, statut et '
            .'priorité. À utiliser quand un membre de l\'équipe demande ce qu\'il reste à traiter, '
            .'ce qui est urgent ou où en est la file des demandes. Aucun paramètre. '
            .'⚠️ Ne pas confondre avec `mes_demandes`, qui répond à un CLIENT sur ses propres dossiers.';
    }

    protected function permission(): AdminPermission
    {
        return AdminPermission::TRAITER_DEMANDES;
    }

    public function run(array $input, AssistantContext $context): ToolResult
    {
        $url = $this->boUrl('demandes');

        // « À traiter » = tout ce qui n'est pas clôturé. La machine à états
        // n'ayant qu'une seule sortie (`CLOTURE`, atteignable depuis n'importe
        // quelle étape), c'est la définition exacte d'un dossier encore vivant —
        // et elle survivra à l'ajout d'une étape intermédiaire, ce qu'une liste
        // de statuts énumérés ne ferait pas.
        $ouvertes = ServiceRequest::query()
            ->where('status', '!=', RequestStatus::CLOTURE->value)
            ->orderByRaw("CASE priority WHEN 'urgente' THEN 0 WHEN 'haute' THEN 1 ELSE 2 END")
            ->oldest();

        $total = (clone $ouvertes)->count();

        if ($total === 0) {
            return $this->nothing(
                'Aucune demande n\'attend d\'être traitée.',
                'Ouvrir la file des demandes',
                $url,
            );
        }

        $demandes = $ouvertes->limit($this->limit())->get();

        return new ToolResult(
            summary: $total > $demandes->count()
                ? $total.' demandes attendent d\'être traitées. Les '.$demandes->count()
                    .' plus prioritaires :'
                : $total.' demande(s) attend(ent) d\'être traitée(s) :',
            items: $demandes->map(fn (ServiceRequest $demande) => array_filter([
                'titre' => $demande->service_type?->label() ?? 'Demande',
                'statut' => $demande->status?->label(),
                'lieu' => $demande->city,
                'detail' => $this->urgence($demande),
                'reference' => $demande->reference,
                'url' => $url.'/'.$demande->id,
            ], fn ($valeur) => $valeur !== null))->all(),
            actions: [AssistantAction::link('Ouvrir la file des demandes', $url)],
        );
    }

    /**
     * Mention d'urgence, et seulement quand il y en a une.
     *
     * Écrire « priorité normale » sur les trois quarts des lignes reviendrait à
     * n'écrire nulle part que l'une d'elles est urgente : dans une file, le
     * signal ne vaut que par son absence ailleurs.
     */
    private function urgence(ServiceRequest $demande): ?string
    {
        return match ($demande->priority?->value) {
            'urgente' => '⚠️ Urgente',
            'haute' => 'Priorité haute',
            default => null,
        };
    }
}
