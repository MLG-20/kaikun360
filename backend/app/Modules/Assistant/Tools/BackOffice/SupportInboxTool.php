<?php

namespace App\Modules\Assistant\Tools\BackOffice;

use App\Models\Conversation;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Assistant\Support\AssistantAction;
use App\Modules\Assistant\Support\AssistantContext;
use App\Modules\Assistant\Support\ToolResult;
use App\Modules\Core\Enums\UserRole;

/**
 * « Qui attend une réponse ? » (phase F10.3).
 *
 * La boîte de réception du support (F8.12) a une particularité : chaque ligne
 * est quelqu'un qui attend, et le retard ne se voit que si l'on ouvre l'écran.
 * C'est le meilleur candidat de la tranche pour une bulle — la question se pose
 * plusieurs fois par jour et sa réponse tient en un nombre.
 *
 * ── Deux chiffres, pas un ───────────────────────────────────────────────────
 * L'outil renvoie **mes fils ouverts** ET **le nombre de fils non assignés**.
 * Le second n'est pas un détail : un fil sans agent n'est celui de personne, il
 * ne remonte dans aucune boîte par défaut, et c'est exactement le cas qui
 * s'oublie. Le taire ferait répondre « rien à traiter » à un agent pendant que
 * trois clients attendent dans le vide.
 *
 * ── Ce qui délimite un fil de support ───────────────────────────────────────
 * ⚠️ `whereNotNull('last_message_at')` est **recopié du contrôleur** et ce n'est
 * pas un filtre de confort : il écarte les échanges qui n'ont jamais concerné
 * l'équipe. Sans lui, l'assistant compterait dans la file du support des
 * conversations privées entre un client et un propriétaire.
 *
 * ── Lecture seule ──────────────────────────────────────────────────────────
 * L'outil ne répond pas, ne prend pas le dossier et ne clôt rien. Répondre au
 * nom d'un agent, dans un fil que le client relira, engage Kaikun 360 par écrit.
 */
class SupportInboxTool extends BackOfficeTool
{
    public function name(): string
    {
        return 'fils_support';
    }

    public function description(): string
    {
        return 'Fait le point sur la boîte du support : les fils ouverts assignés au membre de '
            .'l\'équipe connecté, lesquels attendent sa réponse, et combien de fils ne sont assignés '
            .'à personne. À utiliser quand il demande ses messages, ce qui attend une réponse, ou '
            .'l\'état du support. Aucun paramètre. ⚠️ Lecture seule : cet outil ne répond à aucun fil.';
    }

    protected function permission(): AdminPermission
    {
        return AdminPermission::REPONDRE_MESSAGES;
    }

    public function run(array $input, AssistantContext $context): ToolResult
    {
        $url = $this->boUrl('messages');

        $miens = $this->filsOuverts()
            ->where('assigned_agent_id', $context->user->id);

        $total = (clone $miens)->count();
        // ⚠️ Compté sur une requête NEUVE, pas sur `$miens` : `whereNull` sur une
        // requête déjà filtrée par `assigned_agent_id` ne pourrait rien ramener.
        $orphelins = $this->filsOuverts()->whereNull('assigned_agent_id')->count();

        if ($total === 0) {
            return $this->nothing(
                $orphelins === 0
                    ? 'Aucun fil ouvert ne vous est assigné, et aucun n\'attend d\'être pris.'
                    : 'Aucun fil ne vous est assigné, mais '.$orphelins
                        .' fil(s) n\'ont encore été pris par personne.',
                $orphelins === 0 ? 'Ouvrir la boîte du support' : 'Voir les fils non assignés',
                $url,
            );
        }

        $fils = $miens
            // Ordre de l'écran (F8.12) : l'activité la plus récente en tête.
            ->with(['latestMessage.sender.roles'])
            ->orderByDesc('last_message_at')
            ->limit($this->limit())
            ->get();

        return new ToolResult(
            summary: $this->phrase($total, $orphelins, $fils->count()),
            items: $fils->map(fn (Conversation $fil) => array_filter([
                'titre' => $fil->subject ?: 'Conversation avec le support',
                'statut' => $this->attendReponse($fil) ? 'Attend votre réponse' : 'Répondu',
                'detail' => $fil->last_message_at?->diffForHumans(),
                'url' => $url.'/'.$fil->id,
            ], fn ($valeur) => $valeur !== null))->all(),
            actions: [AssistantAction::link('Ouvrir la boîte du support', $url)],
        );
    }

    /**
     * Les fils de SUPPORT encore ouverts, tous agents confondus.
     *
     * Base commune aux deux comptages, pour que « mes fils » et « les non
     * assignés » ne puissent pas diverger sur ce qu'est un fil vivant.
     */
    private function filsOuverts()
    {
        return Conversation::query()
            ->whereNotNull('last_message_at')
            ->whereNull('closed_at');
    }

    /**
     * Ce fil attend-il l'équipe ?
     *
     * Même règle qu'`AdminConversationResource::awaiting_reply` : le dernier
     * message ne vient pas d'un membre de l'équipe. Un fil dont le client a
     * écrit en dernier n'est pas « lu », il est **dû**.
     *
     * ⚠️ On lit le RÔLE, pas la permission — un agent qui n'a plus
     * `repondre:messages` reste de l'équipe ; le compter comme client ferait
     * réapparaître en « à traiter » un fil auquel on vient de répondre.
     */
    private function attendReponse(Conversation $fil): bool
    {
        $dernier = $fil->latestMessage?->sender;

        return $dernier === null || ! $dernier->hasAnyRole(UserRole::staff());
    }

    /**
     * Phrase de synthèse : mes fils, ce qui est tronqué, et les orphelins.
     */
    private function phrase(int $total, int $orphelins, int $affiches): string
    {
        $debut = $total > $affiches
            ? $total.' fils ouverts vous sont assignés. Les '.$affiches.' plus récents :'
            : $total.' fil(s) ouvert(s) vous sont assignés :';

        return $orphelins === 0
            ? $debut
            : $debut.' (et '.$orphelins.' fil(s) que personne n\'a encore pris)';
    }
}
