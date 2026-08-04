<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Support\Messaging\ConversationContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Représentation JSON d'une conversation pour l'espace client (F3.7).
 *
 * Pensée pour DEUX usages :
 *   - la LISTE des fils (« Messages ») : correspondant(s), aperçu du dernier
 *     message, nombre de non-lus, horodatage d'activité ;
 *   - le DÉTAIL d'un fil : en plus, la collection `messages` si elle est chargée.
 *
 * Les données dépendant de l'utilisateur courant (`counterparts` = les AUTRES
 * participants, `unread_count`) sont calculées à partir de `$request->user()`.
 * Les relations ne sont projetées que si elles ont été chargées en amont
 * (`whenLoaded`), pour maîtriser les requêtes.
 *
 * @mixin \App\Models\Conversation
 */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'subject' => $this->subject,
            // Étiquette lisible du contexte (« Demande », « Réservation »…), si présent.
            'context_label' => $this->contextLabel(),
            // Interlocuteur NOMMÉ du support (F8.12). Le client doit savoir qui
            // lui répond : c'est le même arbitrage produit qu'en F8.11 sur le
            // devis — « le contact humain fait la confiance ».
            'assigned_agent' => $this->whenLoaded('assignedAgent', fn () => $this->assignedAgent ? [
                'id' => $this->assignedAgent->id,
                'name' => $this->assignedAgent->name,
            ] : null),
            // Fil clos = réglé. Le client le voit en lecture et le rouvre en
            // écrivant à nouveau (cf. MessageController::store).
            'is_closed' => $this->isClosed(),
            'closed_at' => $this->closed_at,
            // Les AUTRES participants (le correspondant, du point de vue courant).
            'counterparts' => $this->whenLoaded('participants', fn () => $this->participants
                ->reject(fn ($p) => $p->id === $user?->id)
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    // F8.12.c — depuis qu'un propriétaire ou un prestataire peut
                    // entrer dans le fil, savoir À QUI l'on parle devient
                    // nécessaire : « Ousmane, propriétaire » n'appelle pas la
                    // même réponse que « Awa, support Kaikun ». On expose le
                    // rôle et **jamais les coordonnées** (cf. ContactMasker).
                    'role' => $p->estStaff() ? 'Support Kaikun' : $this->roleLabel($p),
                    'is_team' => $p->estStaff(),
                ])
                ->values()),
            // Aperçu du dernier message pour la liste (corps tronqué).
            'last_message' => $this->whenLoaded('latestMessage', fn () => $this->latestMessage ? [
                'body' => Str::limit((string) $this->latestMessage->body, 100),
                'is_mine' => $this->latestMessage->sender_id === $user?->id,
                'created_at' => $this->latestMessage->created_at,
            ] : null),
            // Fil complet (uniquement sur l'écran de détail, quand chargé).
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            // Messages non lus pour l'utilisateur courant (pastille de la liste).
            'unread_count' => $user ? $this->unreadCountFor($user) : 0,
            'last_message_at' => $this->last_message_at,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Rôle lisible d'un participant non-staff (« Propriétaire », « Prestataire »,
     * « Client »…), lu dans l'enum plutôt que recopié.
     */
    private function roleLabel(User $participant): ?string
    {
        $role = $participant->getRoleNames()->first();

        return $role === null ? null : UserRole::tryFrom($role)?->label();
    }

    /**
     * Étiquette française du contexte polymorphe facultatif, déduite du nom court
     * de la classe (sans coupler cette ressource transversale aux modèles métier).
     */
    private function contextLabel(): ?string
    {
        if ($this->context_type === null) {
            return null;
        }

        // Depuis F8.12, la table des contextes admis est unique et partagée avec
        // la validation du dépôt : la recopier ici ferait diverger ce que le
        // serveur accepte et ce qu'il sait afficher.
        return ConversationContext::labelForClass((string) $this->context_type);
    }
}
