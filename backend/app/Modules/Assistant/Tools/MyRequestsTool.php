<?php

namespace App\Modules\Assistant\Tools;

use App\Models\ServiceRequest;
use App\Modules\Assistant\Support\AssistantAction;
use App\Modules\Assistant\Support\AssistantContext;
use App\Modules\Assistant\Support\ToolResult;
use App\Modules\Core\Enums\UserRole;

/**
 * « Ma demande a-t-elle été traitée ? » (phase F10.2).
 *
 * Une demande de visite, de devis ou de service part souvent le soir et reste
 * sans nouvelle jusqu'à ce qu'un agent la prenne. C'est exactement le moment où
 * l'on écrit au support pour demander « vous avez bien reçu ? » — une question
 * que la plateforme peut trancher seule, en une seconde.
 *
 * ⚠️ Scope de `RequestController::my`, recopié : `where('user_id', …)`.
 *
 * ⚠️ **Réservé au CLIENT, et pas à l'entreprise — malgré les apparences.**
 * Les deux espaces ont bien un écran « Mes demandes », mais ce ne sont pas les
 * mêmes demandes : `/espace-entreprise/demandes` liste des **demandes de team
 * building** (`GET /team-building-requests/mine`), pas des `ServiceRequest`.
 * Ouvrir cet outil à l'entreprise aurait produit des fiches dont le lien —
 * construit sur l'identifiant d'une `ServiceRequest` — pointerait vers l'écran
 * d'un tout autre registre : au mieux une fiche introuvable, au pire la demande
 * de team building qui porte le même numéro. Deux écrans homonymes ne sont pas
 * un écran commun.
 */
class MyRequestsTool extends PersonalRecordsTool
{
    public function name(): string
    {
        return 'mes_demandes';
    }

    public function description(): string
    {
        return 'Consulte les demandes déposées par la personne connectée (visite, devis, service) : '
            .'référence, type, ville, statut. À utiliser quand elle demande où en est SA demande, '
            .'si elle a bien été reçue, ou si quelqu\'un l\'a traitée. Aucun paramètre.';
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
        $url = $this->spaceUrl($context, 'demandes');

        $requests = ServiceRequest::query()
            ->where('user_id', $context->user->id)
            ->latest()
            ->limit($this->limit())
            ->get();

        if ($requests->isEmpty()) {
            return $this->nothing(
                'Je ne trouve aucune demande à votre nom.',
                'Déposer une demande',
                $url,
            );
        }

        return new ToolResult(
            summary: $requests->count() === 1
                ? 'Voici votre demande :'
                : 'Voici vos '.$requests->count().' demandes les plus récentes :',
            items: $requests->map(fn (ServiceRequest $demande) => [
                'reference' => $demande->reference,
                'titre' => $demande->service_type?->label() ?? 'Demande',
                'statut' => $demande->status?->label(),
                'lieu' => $demande->city,
                // ⚠️ Le MESSAGE de la demande n'est pas renvoyé, bien qu'il soit
                // à la personne : il peut être long, il n'aide pas à répondre
                // « où ça en est », et tout champ libre qui traverse la bulle
                // est un champ de plus à surveiller le jour du driver Claude.
                'url' => $url.'/'.$demande->id,
            ])->all(),
            actions: [AssistantAction::link('Voir toutes mes demandes', $url)],
        );
    }
}
