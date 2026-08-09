<?php

namespace App\Modules\Assistant\Tools;

use App\Modules\Assistant\Support\AssistantAction;
use App\Modules\Assistant\Support\AssistantContext;
use App\Modules\Assistant\Support\ToolResult;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Diaspora\Models\DiasporaProject;

/**
 * « Où en est mon projet au pays ? » (phase F10.2).
 *
 * Le public de la diaspora est celui pour qui cet outil compte le plus : on
 * suit de loin un chantier ou un achat, souvent avec plusieurs heures de
 * décalage horaire, c'est-à-dire **en dehors des heures où le support répond**.
 * Une réponse immédiate sur l'état du dossier et le nombre de comptes rendus
 * déposés évite une nuit d'attente.
 *
 * ⚠️ **« Diaspora » n'est PAS un rôle de sécurité** — c'est un `ProfileType`.
 * Au sens des rôles, ces comptes sont des **clients**, et c'est bien ce que
 * `DiasporaProject::$client_id` reflète. L'outil est donc ouvert au rôle
 * `client` et se tait de lui-même quand la personne n'a aucun projet : inventer
 * un rôle « diaspora » ici aurait créé une seconde hiérarchie d'autorisations à
 * côté de celle qui existe.
 *
 * ⚠️ Scope de `DiasporaProjectController::mine`, recopié :
 * `where('client_id', …)`. La `DiasporaProjectPolicy` gouverne le détail d'un
 * projet (client, agent affecté, équipe) ; ici on ne liste que les siens, ce que
 * la même policy autorise par construction.
 */
class MyDiasporaProjectsTool extends PersonalRecordsTool
{
    public function name(): string
    {
        return 'mes_projets_diaspora';
    }

    public function description(): string
    {
        return 'Consulte les projets diaspora du client connecté (achat, construction, suivi de '
            .'chantier depuis l\'étranger) : référence, type, statut, budget et nombre de comptes '
            .'rendus déposés par l\'agent. À utiliser quand il demande où en est SON projet au '
            .'pays. Aucun paramètre.';
    }

    /**
     * @return array<int, UserRole>
     */
    protected function roles(): array
    {
        return [UserRole::CLIENT];
    }

    public function run(array $input, AssistantContext $context): ToolResult
    {
        $url = $this->spaceUrl($context, 'diaspora');

        $projects = DiasporaProject::query()
            ->where('client_id', $context->user->id)
            // Le nombre de comptes rendus est LA mesure d'avancement que le
            // client attend ; `withCount` l'obtient sans charger les rapports,
            // qui contiennent des observations d'agent hors sujet ici.
            ->withCount('reports')
            ->latest()
            ->limit($this->limit())
            ->get();

        if ($projects->isEmpty()) {
            return $this->nothing(
                'Je ne trouve aucun projet diaspora à votre nom.',
                'Ouvrir un projet',
                $url,
            );
        }

        return new ToolResult(
            summary: $projects->count() === 1
                ? 'Voici votre projet :'
                : 'Voici vos '.$projects->count().' projets les plus récents :',
            items: $projects->map(fn (DiasporaProject $projet) => [
                'reference' => $projet->reference,
                'titre' => $projet->project_type?->label() ?? 'Projet diaspora',
                'statut' => $projet->status?->label(),
                'montant' => $this->money($projet->budget_xof),
                'detail' => $projet->reports_count === 0
                    ? 'Aucun compte rendu déposé'
                    : $projet->reports_count.' compte(s) rendu(s)',
                'url' => $url,
            ])->all(),
            actions: [AssistantAction::link('Voir mes projets', $url)],
        );
    }
}
