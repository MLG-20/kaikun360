<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartConversationRequest;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Notifications\NewMessageNotification;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Messagerie de l'espace client — couche transversale (phase F3.7).
 *
 * Socle GÉNÉRIQUE réutilisable par les espaces pro (F4/F5/F6) : conversations à
 * participants + messages. Deux invariants de sécurité gouvernent tout le
 * contrôleur :
 *   1. on ne manipule QUE les conversations dont l'utilisateur courant est
 *      participant — l'accès passe systématiquement par la relation
 *      `->conversations()` (jamais un `Conversation::find` global) : un fil
 *      d'autrui renvoie 404, sans fuite ;
 *   2. les non-lus se calculent par participant via le `last_read_at` du pivot.
 */
class MessageController extends Controller
{
    /**
     * Liste paginée de mes conversations, les plus actives d'abord.
     * GET /api/v1/messages
     *
     * On joint `unread_count` (total, tous fils confondus) aux métadonnées pour
     * la pastille de menu, sans second appel réseau.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $conversations = $user->conversations()
            // Correspondant(s) + aperçu du dernier message pour la liste.
            ->with(['participants', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->paginate(15);

        return ConversationResource::collection($conversations)
            ->additional(['unread_count' => $this->totalUnread($request)]);
    }

    /**
     * Détail d'une conversation : ses messages, du plus ancien au plus récent.
     * GET /api/v1/messages/{conversation}
     *
     * L'accès est scopé à mes fils (404 si je n'y participe pas). Ouvrir le fil
     * le marque comme lu (mise à jour de MON `last_read_at` sur le pivot).
     */
    public function show(Request $request, Conversation $conversation): ConversationResource
    {
        $user = $request->user();

        // Accès scopé : `findOrFail` sur la relation → 404 si non participant.
        $conversation = $user->conversations()
            ->with(['participants', 'messages.sender'])
            ->findOrFail($conversation->id);

        // Marque comme lu : tout est vu jusqu'à maintenant.
        $user->conversations()->updateExistingPivot($conversation->id, [
            'last_read_at' => now(),
        ]);

        return ConversationResource::make($conversation);
    }

    /**
     * Envoie un message dans une conversation existante.
     * POST /api/v1/messages/{conversation}/messages
     *
     * Met à jour `last_message_at` (tri des fils), remet MON `last_read_at` à
     * jour (mon propre message n'est pas « non lu ») et notifie les AUTRES
     * participants (canal `database`, cloche + écran « Mes notifications »).
     */
    public function store(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        // Accès scopé : 404 si l'utilisateur ne participe pas au fil.
        $conversation = $user->conversations()->findOrFail($conversation->id);

        $message = DB::transaction(function () use ($conversation, $user, $request) {
            $message = $conversation->messages()->create([
                'sender_id' => $user->id,
                'body' => $request->validated('body'),
            ]);

            $conversation->update(['last_message_at' => $message->created_at]);
            $user->conversations()->updateExistingPivot($conversation->id, [
                'last_read_at' => $message->created_at,
            ]);

            return $message;
        });

        $this->notifyOthers($conversation, $user->id, $message);

        return ApiResponse::created([
            'message' => MessageResource::make($message->load('sender')),
        ]);
    }

    /**
     * Ouvre une nouvelle conversation avec un destinataire et poste le premier
     * message. POST /api/v1/messages
     *
     * Idempotence légère : si un fil SANS contexte existe déjà entre exactement
     * ces deux participants, on y ajoute le message au lieu d'en créer un doublon.
     */
    public function start(StartConversationRequest $request): JsonResponse
    {
        $user = $request->user();
        $recipientId = (int) $request->validated('recipient_id');

        $result = DB::transaction(function () use ($request, $user, $recipientId) {
            $conversation = $this->findDirectConversation($user->id, $recipientId)
                ?? Conversation::create(['subject' => $request->validated('subject')]);

            // Rattache les deux participants sans dupliquer (unique sur le pivot).
            $conversation->participants()->syncWithoutDetaching([$user->id, $recipientId]);

            $message = $conversation->messages()->create([
                'sender_id' => $user->id,
                'body' => $request->validated('body'),
            ]);

            $conversation->update(['last_message_at' => $message->created_at]);
            $user->conversations()->updateExistingPivot($conversation->id, [
                'last_read_at' => $message->created_at,
            ]);

            return [$conversation, $message];
        });

        [$conversation, $message] = $result;

        $this->notifyOthers($conversation, $user->id, $message);

        return ApiResponse::created([
            'conversation' => ConversationResource::make(
                $conversation->load(['participants', 'latestMessage'])
            ),
        ]);
    }

    /**
     * Nombre total de messages non lus, tous fils confondus (pastille de menu).
     * GET /api/v1/messages/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'unread_count' => $this->totalUnread($request),
        ]);
    }

    /**
     * Somme des non-lus sur toutes mes conversations. À l'échelle d'un utilisateur
     * (peu de fils), la lecture par conversation reste largement suffisante et
     * garde le calcul lisible.
     */
    private function totalUnread(Request $request): int
    {
        $user = $request->user();

        return $user->conversations()
            ->with('participants')
            ->get()
            ->sum(fn (Conversation $conversation) => $conversation->unreadCountFor($user));
    }

    /**
     * Retrouve une conversation directe (sans contexte, exactement 2 participants
     * = l'auteur et le destinataire), pour éviter d'empiler les doublons.
     */
    private function findDirectConversation(int $userId, int $recipientId): ?Conversation
    {
        return Conversation::query()
            ->whereNull('context_type')
            ->whereHas('participants', fn ($q) => $q->whereKey($userId))
            ->whereHas('participants', fn ($q) => $q->whereKey($recipientId))
            ->withCount('participants')
            ->having('participants_count', '=', 2)
            ->first();
    }

    /**
     * Notifie tous les participants d'un fil SAUF l'auteur du message.
     */
    private function notifyOthers(Conversation $conversation, int $senderId, Message $message): void
    {
        $conversation->participants()
            ->where('users.id', '!=', $senderId)
            ->get()
            ->each
            ->notify(new NewMessageNotification($message));
    }
}
