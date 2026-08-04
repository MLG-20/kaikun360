<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartConversationRequest;
use App\Http\Requests\StartSupportConversationRequest;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Services\SupportAssignmentService;
use App\Support\ApiResponse;
use App\Support\Messaging\ConversationContext;
use Illuminate\Database\Eloquent\Model;
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
            // `assignedAgent` : le client voit le nom de qui lui répond (F8.12).
            // `participants.roles` : la ressource affiche le RÔLE de chaque
            // participant depuis F8.12.c — sans ce chargement, un N+1 par fil.
            ->with(['participants.roles', 'assignedAgent', 'latestMessage'])
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

        // `?after=<id>` — RELÈVE PÉRIODIQUE (F8.12.a) : l'écran qui a déjà le fil
        // ne redemande que ce qui a été écrit depuis son dernier message. Sans
        // ce paramètre, un fil ouvert dix minutes retéléchargerait tout
        // l'historique toutes les dix secondes.
        $after = $request->integer('after') ?: null;

        // Accès scopé : `findOrFail` sur la relation → 404 si non participant.
        $conversation = $user->conversations()
            ->with([
                'participants.roles',
                'assignedAgent',
                'messages' => fn ($query) => $query
                    ->when($after, fn ($q) => $q->where('id', '>', $after))
                    ->with('sender'),
            ])
            ->findOrFail($conversation->id);

        // Marque comme lu : tout est vu jusqu'à maintenant. ⚠️ En relève, on
        // n'écrit que s'il y a réellement du nouveau — sinon chaque battement
        // produirait une écriture en base pour ne rien changer.
        if ($after === null || $conversation->messages->isNotEmpty()) {
            $user->conversations()->updateExistingPivot($conversation->id, [
                'last_read_at' => now(),
            ]);
        }

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

            // Un nouveau message ROUVRE un fil clos (F8.12) : le client qui
            // relance un dossier réglé doit réapparaître dans la file de
            // l'agent, sinon sa relance n'est vue par personne.
            $conversation->update([
                'last_message_at' => $message->created_at,
                'closed_at' => null,
            ]);
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
                $conversation->load(['participants.roles', 'latestMessage'])
            ),
        ]);
    }

    /**
     * Ouvre (ou reprend) un fil AVEC LE SUPPORT KAIKUN, et poste le premier
     * message. POST /api/v1/messages/support — F8.12.
     *
     * C'est LE geste qui manquait depuis F3.7 : le socle savait tout faire sauf
     * commencer. `startConversation()` existait côté frontend et n'était appelé
     * par aucun écran ; l'état vide de « Mes messages » promettait un bouton qui
     * n'existait nulle part.
     *
     * Trois différences avec `start()` :
     *   1. **Aucun destinataire fourni** : le serveur choisit l'agent de
     *      permanence. Le client n'écrit jamais directement au propriétaire ou au
     *      prestataire — c'est l'agent qui décidera, au cas par cas, d'ajouter le
     *      tiers au fil.
     *   2. **Le dossier concerné est rattaché** (`context_type`/`context_id`),
     *      pour que l'agent sache de quoi on lui parle sans un aller-retour.
     *   3. **Reprise plutôt que doublon** : réécrire à propos du MÊME dossier
     *      continue le fil ouvert au lieu d'en empiler un second — pour le
     *      client comme pour l'agent, c'est la même conversation.
     *
     * ⚠️ Si aucun agent ne porte `repondre:messages`, le fil est créé quand même,
     * sans responsable : il tombera dans « Non assignés » au back-office. Perdre
     * le message serait pire que de le faire attendre visiblement.
     */
    public function startWithSupport(
        StartSupportConversationRequest $request,
        SupportAssignmentService $assignment,
    ): JsonResponse {
        $user = $request->user();

        // Résolution du dossier AVANT la transaction : un contexte invalide ou
        // qui ne regarde pas cet utilisateur est simplement ignoré (le message
        // part quand même — refuser laisserait le client sans recours).
        $context = ConversationContext::resolve(
            $request->validated('context_type'),
            $request->integer('context_id') ?: null,
            $user,
        );

        $agent = $assignment->pick();

        [$conversation, $message] = DB::transaction(function () use ($request, $user, $context, $agent) {
            $conversation = $this->findSupportConversation($user, $context);

            if ($conversation === null) {
                $conversation = Conversation::create([
                    'subject' => $request->validated('subject')
                        ?: $this->defaultSubject($context),
                    'assigned_agent_id' => $agent?->id,
                    'context_type' => $context?->getMorphClass(),
                    'context_id' => $context?->getKey(),
                ]);
            } elseif ($conversation->isClosed()) {
                // Rouvrir : le client relance un dossier qu'on croyait réglé.
                $conversation->forceFill(['closed_at' => null])->save();
            }

            // Le client et l'agent responsable participent au fil. `syncWithoutDetaching`
            // n'enlève personne : sur un fil repris, un tiers déjà ajouté par
            // l'agent y reste.
            $conversation->participants()->syncWithoutDetaching(
                array_filter([$user->id, $conversation->assigned_agent_id]),
            );

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

        $this->notifyOthers($conversation, $user->id, $message);

        return ApiResponse::created([
            'conversation' => ConversationResource::make(
                $conversation->load(['participants.roles', 'assignedAgent', 'latestMessage'])
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
     * Retrouve le fil de support de cet utilisateur pour CE dossier (ou le fil
     * de support général si aucun dossier n'est cité), afin de le reprendre au
     * lieu d'en ouvrir un second. F8.12.
     *
     * Un fil de support se reconnaît à son `assigned_agent_id` renseigné — ou,
     * cas limite, à un fil né sans agent de permanence disponible, qu'il faut
     * bien pouvoir reprendre lui aussi. On ne remonte que les fils dont
     * l'utilisateur est déjà participant.
     */
    private function findSupportConversation(User $user, ?Model $context): ?Conversation
    {
        return $user->conversations()
            ->when(
                $context !== null,
                fn ($query) => $query
                    ->where('context_type', $context->getMorphClass())
                    ->where('context_id', $context->getKey()),
                // Fil « général » : sans dossier attaché, il n'y en a qu'un.
                fn ($query) => $query->whereNull('context_type'),
            )
            ->orderByDesc('last_message_at')
            ->first();
    }

    /**
     * Sujet par défaut d'un fil de support : le nom du dossier concerné, pour
     * que la liste des fils reste lisible même sans sujet saisi.
     */
    private function defaultSubject(?Model $context): string
    {
        $label = ConversationContext::labelForClass($context ? $context::class : null);

        return $label !== null
            ? $label.' — '.($context->getAttribute('reference') ?? '#'.$context->getKey())
            : 'Question au support Kaikun';
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
