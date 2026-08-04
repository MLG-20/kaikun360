<?php

namespace App\Modules\Admin\Http\Resources;

use App\Models\Conversation;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Support\Messaging\ConversationContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Représentation JSON d'un fil de support pour le **back-office** (F8.12).
 *
 * `ConversationResource` sert l'espace client : le client sait qui il est, on
 * lui montre donc « son » correspondant. Côté équipe, la question est inverse —
 * l'agent doit savoir **qui écrit, à propos de quoi, et depuis combien de
 * temps ça attend**. D'où trois blocs absents de la ressource client :
 *
 *   - `requester` : l'interlocuteur non-staff, avec ses coordonnées (l'agent
 *     doit pouvoir rappeler sans quitter l'écran, comme dans la file des
 *     demandes F8.9) ;
 *   - `context` : le dossier cité, étiqueté et référencé ;
 *   - `awaiting_reply` : le fil attend-il une réponse de l'équipe ? C'est le
 *     seul chiffre qui gouverne le travail — un fil dont le dernier message
 *     vient du client n'est pas « lu », il est **dû**.
 *
 * ⚠️ Servie uniquement derrière la garde `repondre:messages`.
 *
 * @mixin Conversation
 */
class AdminConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $agent = $request->user();

        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'context_label' => ConversationContext::labelForClass($this->context_type),
            'context_type' => $this->context_type,
            'context_id' => $this->context_id,
            'is_closed' => $this->isClosed(),
            'closed_at' => $this->closed_at,
            'last_message_at' => $this->last_message_at,
            'created_at' => $this->created_at,

            // Agent responsable — `null` = personne, le fil est dans la file
            // « Non assignés » et doit être pris par quelqu'un.
            'assigned_agent' => $this->whenLoaded('assignedAgent', fn () => $this->assignedAgent ? [
                'id' => $this->assignedAgent->id,
                'name' => $this->assignedAgent->name,
            ] : null),
            'is_mine' => $this->assigned_agent_id === $agent?->id,

            // L'interlocuteur du fil, côté public.
            'requester' => $this->whenLoaded('participants', fn () => $this->requester()),

            // Les tiers ajoutés au fil par l'équipe (propriétaire, prestataire) :
            // prénom + rôle, JAMAIS les coordonnées (cf. l'écran).
            'others' => $this->whenLoaded('participants', fn () => $this->others()),

            'last_message' => $this->whenLoaded('latestMessage', fn () => $this->latestMessage ? [
                'body' => Str::limit((string) $this->latestMessage->body, 120),
                'from_team' => $this->isStaff($this->latestMessage->sender),
                'created_at' => $this->latestMessage->created_at,
            ] : null),

            // Le fil attend-il l'équipe ? Vrai tant que le dernier message ne
            // vient pas d'un membre du staff (et que le fil est ouvert).
            'awaiting_reply' => $this->whenLoaded('latestMessage', fn () => ! $this->isClosed()
                && $this->latestMessage !== null
                && ! $this->isStaff($this->latestMessage->sender)),

            'messages' => \App\Http\Resources\MessageResource::collection($this->whenLoaded('messages')),
        ];
    }

    /**
     * L'interlocuteur côté public : **celui qui a ouvert le fil**.
     *
     * ⚠️ On le reconnaît à l'auteur du PREMIER message, pas au « premier
     * participant non-staff ». Défaut trouvé en éprouvant l'ajout d'un tiers sur
     * des données réelles : dès qu'un propriétaire entre dans la conversation,
     * l'ancienne règle pouvait le désigner à la place du client — la fiche
     * affichait alors le mauvais nom et proposait de rappeler la mauvaise
     * personne. L'ancienne règle sert de repli quand la relation n'est pas
     * chargée.
     *
     * @return array<string, mixed>|null
     */
    private function requester(): ?array
    {
        $auteurId = $this->relationLoaded('firstMessage') ? $this->firstMessage?->sender_id : null;

        $user = ($auteurId !== null ? $this->participants->firstWhere('id', $auteurId) : null)
            ?? $this->participants->first(fn (User $p) => ! $this->isStaff($p));

        return $user === null ? null : [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->getRoleNames()->first(),
        ];
    }

    /**
     * Les AUTRES participants (tiers ajoutés au fil, agents supplémentaires) :
     * identité et rôle seulement.
     *
     * @return array<int, array<string, mixed>>
     */
    private function others(): array
    {
        $requesterId = $this->requester()['id'] ?? null;

        return $this->participants
            ->reject(fn (User $p) => $p->id === $requesterId)
            ->map(fn (User $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'role' => $p->getRoleNames()->first(),
                'is_team' => $this->isStaff($p),
            ])
            ->values()
            ->all();
    }

    /**
     * Ce participant est-il de l'équipe Kaikun ?
     *
     * On lit le RÔLE et non la permission : un agent qui n'a pas (ou plus)
     * `repondre:messages` reste un membre de l'équipe — le compter comme client
     * ferait apparaître deux « demandeurs » dans le fil.
     */
    private function isStaff(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole([
            UserRole::AGENT_KAIKUN->value,
            UserRole::ADMIN->value,
            UserRole::SUPER_ADMIN->value,
        ]);
    }
}
