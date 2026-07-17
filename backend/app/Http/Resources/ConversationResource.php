<?php

namespace App\Http\Resources;

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
            // Les AUTRES participants (le correspondant, du point de vue courant).
            'counterparts' => $this->whenLoaded('participants', fn () => $this->participants
                ->reject(fn ($p) => $p->id === $user?->id)
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])
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
     * Étiquette française du contexte polymorphe facultatif, déduite du nom court
     * de la classe (sans coupler cette ressource transversale aux modèles métier).
     */
    private function contextLabel(): ?string
    {
        if ($this->context_type === null) {
            return null;
        }

        return match (class_basename((string) $this->context_type)) {
            'ServiceRequest' => 'Demande',
            'Booking' => 'Réservation',
            'Property' => 'Bien immobilier',
            default => null,
        };
    }
}
