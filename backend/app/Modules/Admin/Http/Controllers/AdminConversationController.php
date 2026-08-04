<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Http\Resources\AdminConversationResource;
use App\Modules\Core\Enums\UserRole;
use App\Notifications\NewMessageNotification;
use App\Support\ApiResponse;
use App\Support\Messaging\ConversationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Boîte de réception du support — back-office (F8.12).
 *
 * ⚠️ **Sans cet écran, la messagerie n'existe pas.** Depuis F3.7, un client
 * pouvait lire un fil et y répondre, mais personne côté équipe n'avait de vue
 * sur ces fils : un agent aurait dû ouvrir son espace CLIENT personnel pour
 * découvrir, au hasard d'une notification, qu'on lui écrivait. Le CDC liste
 * pourtant « Messages — conversation avec le support Kaikun ou le prestataire
 * affecté » comme module **contractuel, pour tous les profils**.
 *
 * Quatre gestes, et pas un de plus :
 *   - **lire la file** (`index`) : ce qui attend une réponse, en tête ;
 *   - **ouvrir un fil** (`show`) : l'échange complet + de quoi rappeler ;
 *   - **répondre** (`reply`) : et, au passage, prendre le dossier si personne
 *     ne l'avait ;
 *   - **piloter** (`update`) : réassigner, clore, rouvrir.
 *
 * **F8.12.c — l'ajout d'un TIERS** (propriétaire, prestataire) complète la
 * série : `candidates` propose les personnes du dossier puis une recherche
 * restreinte aux professionnels, `addParticipant` les fait entrer,
 * `removeParticipant` les sort. ⚠️ Le geste reste un **jugement au cas par
 * cas** : on ne l'automatise pas. Les coordonnées écrites dans les messages
 * sont masquées entre non-staff (`ContactMasker`) mais **restent lisibles pour
 * l'équipe**, qui doit pouvoir arbitrer un litige.
 */
class AdminConversationController extends Controller
{
    /**
     * File des fils de support. GET /api/v1/admin/conversations
     *
     * Filtres : `scope` (`mine` par défaut, `unassigned`, `all`), `closed`
     * (0/1), `search` (identité ou e-mail de l'interlocuteur, sujet), `per_page`.
     *
     * Par défaut on montre **mes fils ouverts** : une boîte qui s'ouvre sur tout
     * l'historique de l'équipe est une boîte que personne ne traite.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $agent = $request->user();
        $scope = $request->string('scope')->toString() ?: 'mine';

        $conversations = Conversation::query()
            // Seuls les fils de support : un fil né sans agent de permanence en
            // fait partie (il attend justement d'être pris), les échanges qui
            // n'ont jamais concerné l'équipe n'ont rien à faire ici.
            ->whereNotNull('last_message_at')
            ->when($scope === 'mine', fn ($query) => $query->where('assigned_agent_id', $agent->id))
            ->when($scope === 'unassigned', fn ($query) => $query->whereNull('assigned_agent_id'))
            // `closed=1` montre l'archive ; par défaut, ce qui est encore vivant.
            ->when(
                $request->boolean('closed'),
                fn ($query) => $query->whereNotNull('closed_at'),
                fn ($query) => $query->whereNull('closed_at'),
            )
            ->when($request->filled('search'), function ($query) use ($request) {
                $terme = '%'.$request->string('search')->toString().'%';

                $query->where(function ($sous) use ($terme) {
                    $sous->where('subject', 'like', $terme)
                        ->orWhereHas('participants', fn ($p) => $p->where('name', 'like', $terme)
                            ->orWhere('email', 'like', $terme)
                            ->orWhere('phone', 'like', $terme));
                });
            })
            // `firstMessage` : c'est SON auteur qui est le demandeur du fil
            // (cf. AdminConversationResource::requester).
            ->with(['participants.roles', 'assignedAgent', 'firstMessage', 'latestMessage.sender.roles'])
            ->orderByDesc('last_message_at')
            ->paginate(max(1, min(100, (int) $request->integer('per_page', 20))));

        return AdminConversationResource::collection($conversations);
    }

    /**
     * Fiche d'un fil : l'échange complet. GET /api/v1/admin/conversations/{conversation}
     *
     * ⚠️ Contrairement à l'espace client, l'accès n'est PAS scopé aux fils dont
     * on est participant : un agent doit pouvoir reprendre le dossier d'un
     * collègue absent. C'est la permission `repondre:messages` qui fait office
     * de barrière, et la lecture reste tracée par le journal d'activité.
     *
     * Ouvrir le fil le marque comme lu **uniquement si on y participe déjà** —
     * lire par-dessus l'épaule d'un collègue ne doit pas éteindre SA pastille.
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $agent = $request->user();

        // `?after=<id>` — relève périodique (F8.12.a), même contrat que côté
        // client : l'écran ouvert ne redemande que les messages plus récents.
        $after = $request->integer('after') ?: null;

        $conversation->load([
            'participants.roles',
            'assignedAgent',
            'firstMessage',
            'messages' => fn ($query) => $query
                ->when($after, fn ($q) => $q->where('id', '>', $after))
                ->with('sender.roles'),
            'latestMessage.sender.roles',
        ]);

        // Marquage lu : jamais en relève à vide (une écriture par battement
        // pour rien), et jamais pour qui ne participe pas au fil — lire
        // par-dessus l'épaule d'un collègue ne doit pas éteindre SA pastille.
        if (($after === null || $conversation->messages->isNotEmpty())
            && $conversation->participants->contains('id', $agent->id)) {
            $agent->conversations()->updateExistingPivot($conversation->id, [
                'last_read_at' => now(),
            ]);
        }

        return ApiResponse::success([
            'conversation' => AdminConversationResource::make($conversation),
            // Le vivier, pour le sélecteur de réassignation.
            'agents' => $this->assignableAgents(),
        ]);
    }

    /**
     * Répondre au client. POST /api/v1/admin/conversations/{conversation}/messages
     *
     * Deux effets de bord assumés, tous deux voulus :
     *   1. **répondre, c'est prendre le dossier** — un fil sans responsable est
     *      assigné à qui répond le premier. Sans cela, « Non assignés » ne se
     *      viderait jamais et deux agents répondraient au même client ;
     *   2. **répondre, c'est rouvrir** — si le fil avait été clos, la réponse le
     *      remet dans la file (le client peut encore réagir).
     */
    public function reply(Request $request, Conversation $conversation): JsonResponse
    {
        $agent = $request->user();

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ], [
            'body.required' => 'Écrivez votre réponse avant de l’envoyer.',
        ]);

        $message = DB::transaction(function () use ($conversation, $agent, $validated) {
            // Prise en charge implicite (cf. en-tête) + entrée dans le fil.
            if ($conversation->assigned_agent_id === null) {
                $conversation->assigned_agent_id = $agent->id;
            }

            $conversation->participants()->syncWithoutDetaching([$agent->id]);

            $message = $conversation->messages()->create([
                'sender_id' => $agent->id,
                'body' => $validated['body'],
            ]);

            $conversation->fill([
                'last_message_at' => $message->created_at,
                'closed_at' => null,
            ])->save();

            $agent->conversations()->updateExistingPivot($conversation->id, [
                'last_read_at' => $message->created_at,
            ]);

            return $message;
        });

        // Notifie tout le monde SAUF l'auteur (le client, et les tiers du fil).
        $conversation->participants()
            ->where('users.id', '!=', $agent->id)
            ->get()
            ->each
            ->notify(new NewMessageNotification($message));

        return ApiResponse::created([
            'conversation' => AdminConversationResource::make(
                $conversation->fresh()->load(['participants.roles', 'assignedAgent', 'firstMessage', 'messages.sender.roles', 'latestMessage.sender.roles'])
            ),
        ]);
    }

    /**
     * Piloter un fil. PATCH /api/v1/admin/conversations/{conversation}
     *
     * Champs facultatifs, combinables :
     *   - `assigned_agent_id` : réassigner (à un compte du vivier, ou `null`
     *     pour remettre le fil dans « Non assignés ») ;
     *   - `closed` : clore (true) ou rouvrir (false).
     *
     * ⚠️ Réassigner **ne retire personne du fil** : l'agent précédent y reste
     * participant. Le sortir effacerait l'historique de son côté et casserait
     * les notifications déjà parties.
     */
    public function update(Request $request, Conversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'assigned_agent_id' => [
                'sometimes', 'nullable', 'integer',
                // Le destinataire doit appartenir au vivier : assigner un fil à
                // quelqu'un qui n'a pas le droit d'y répondre le rendrait muet.
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->whereIn('id', $this->assignableAgentIds()),
                ),
            ],
            'closed' => ['sometimes', 'boolean'],
        ], [
            'assigned_agent_id.exists' => 'Ce compte ne fait pas partie des agents habilités à répondre.',
        ]);

        if (array_key_exists('assigned_agent_id', $validated)) {
            $conversation->assigned_agent_id = $validated['assigned_agent_id'];

            if ($validated['assigned_agent_id'] !== null) {
                $conversation->participants()->syncWithoutDetaching([$validated['assigned_agent_id']]);
            }
        }

        if (array_key_exists('closed', $validated)) {
            $conversation->closed_at = $validated['closed'] ? now() : null;
        }

        $conversation->save();

        return ApiResponse::success([
            'conversation' => AdminConversationResource::make(
                $conversation->fresh()->load(['participants.roles', 'assignedAgent', 'firstMessage', 'latestMessage.sender.roles'])
            ),
        ]);
    }

    /**
     * Qui l'agent peut faire entrer dans ce fil.
     * GET /api/v1/admin/conversations/{conversation}/candidates?search=
     *
     * Deux listes, dans l'ordre d'utilité :
     *   - `dossier` : la personne rattachée au dossier cité (propriétaire du
     *     bien, hôte de la nuitée, prestataire du circuit…). C'est le cas
     *     courant, et il tient en un clic ;
     *   - `results` : une recherche par nom, **restreinte aux comptes
     *     propriétaire et prestataire**. Elle sert aux fils sans dossier et aux
     *     cas particuliers. La limiter à ces deux rôles évite d'ajouter par
     *     mégarde un client tiers dans une conversation qui ne le regarde pas.
     *
     * Les personnes déjà dans le fil sont retirées des deux listes : proposer
     * d'ajouter quelqu'un qui y est déjà n'a pas de sens.
     */
    public function candidates(Request $request, Conversation $conversation): JsonResponse
    {
        $conversation->load('participants');
        $dejaLa = $conversation->participants->pluck('id')->all();

        $context = $conversation->context_type
            ? ($conversation->context_type)::find($conversation->context_id)
            : null;

        $dossier = ConversationContext::holder($context);

        $recherche = $request->string('search')->toString();

        $resultats = strlen($recherche) < 2 ? collect() : User::query()
            ->role([UserRole::PROPRIETAIRE->value, UserRole::PRESTATAIRE->value])
            ->whereNotIn('id', $dejaLa)
            ->where(fn ($query) => $query->where('name', 'like', "%{$recherche}%")
                ->orWhere('email', 'like', "%{$recherche}%"))
            ->orderBy('name')
            ->limit(10)
            ->get();

        return ApiResponse::success([
            'dossier' => $dossier && ! in_array($dossier->id, $dejaLa, true)
                ? $this->personne($dossier, ConversationContext::labelForClass($conversation->context_type))
                : null,
            'results' => $resultats->map(fn (User $u) => $this->personne($u))->all(),
        ]);
    }

    /**
     * Fait entrer un tiers dans le fil.
     * POST /api/v1/admin/conversations/{conversation}/participants
     *
     * ⚠️ **C'est un jugement, pas une règle.** Une question de disponibilité ou
     * de nombre de chambres se transmet volontiers au propriétaire ; une
     * négociation de prix, l'agent la garde. Automatiser ce geste ferait perdre
     * les deux avantages de l'architecture « support pivot » : la supervision et
     * la rapidité.
     *
     * Le tiers voit **tout l'historique** du fil — c'est voulu, sans quoi il
     * répondrait à une question qu'il n'a pas lue. L'agent doit donc le savoir
     * avant d'ajouter : l'écran le dit.
     */
    public function addParticipant(Request $request, Conversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $tiers = User::findOrFail($validated['user_id']);

        // Garde-fou : seuls des professionnels entrent par ce geste. L'équipe,
        // elle, rejoint un fil en répondant ou par assignation — et un client
        // n'a rien à faire dans la conversation d'un autre client.
        //
        // ⚠️ **Deux portes, et la seconde n'est pas un contournement.** Le rôle
        // suffit ; mais la personne RATTACHÉE AU DOSSIER passe aussi, même sans
        // rôle déclaré. Trouvé en éprouvant le geste sur des données réelles :
        // le propriétaire du bien n'avait aucun rôle Spatie (compte importé),
        // et l'écran refusait d'ajouter… le propriétaire du bien dont on
        // parlait. C'est le dossier qui fait la légitimité, pas la ligne de
        // rôle — et cette porte-là reste étroite : elle ne s'ouvre que pour la
        // personne que le dossier désigne.
        $context = $conversation->context_type
            ? ($conversation->context_type)::find($conversation->context_id)
            : null;

        $estLaPersonneDuDossier = ConversationContext::holder($context)?->id === $tiers->id;

        if (! $estLaPersonneDuDossier
            && ! $tiers->hasAnyRole([UserRole::PROPRIETAIRE->value, UserRole::PRESTATAIRE->value])) {
            return ApiResponse::error(
                'Seuls un propriétaire ou un prestataire peuvent être ajoutés à une conversation.',
                422,
            );
        }

        $conversation->participants()->syncWithoutDetaching([$tiers->id]);

        // Le prévenir : sans notification, il ne saurait pas qu'on l'attend.
        // On réutilise l'alerte de message existante — c'est exact, il a bien
        // des messages à lire.
        $dernier = $conversation->latestMessage()->first();

        if ($dernier !== null) {
            $tiers->notify(new NewMessageNotification($dernier));
        }

        return ApiResponse::success([
            'conversation' => AdminConversationResource::make(
                $conversation->fresh()->load(['participants.roles', 'assignedAgent', 'firstMessage', 'messages.sender.roles', 'latestMessage.sender.roles'])
            ),
        ]);
    }

    /**
     * Sort un tiers du fil.
     * DELETE /api/v1/admin/conversations/{conversation}/participants/{user}
     *
     * ⚠️ **Ni le demandeur, ni l'agent responsable** ne peuvent être retirés :
     * le premier est celui dont c'est la conversation, le second en répond. Les
     * sortir laisserait un fil que son propre auteur ne pourrait plus lire.
     * Les messages déjà écrits par le tiers **restent** — on le sort de la
     * suite de l'échange, on ne réécrit pas l'histoire.
     */
    public function removeParticipant(Conversation $conversation, User $user): JsonResponse
    {
        $conversation->load('participants.roles');

        if ($user->id === $conversation->assigned_agent_id || $user->estStaff()) {
            return ApiResponse::error("L'agent responsable du fil ne peut pas en être retiré.", 422);
        }

        $demandeur = $conversation->participants->first(fn (User $p) => ! $p->estStaff());

        if ($demandeur !== null && $demandeur->id === $user->id) {
            return ApiResponse::error(
                "Cette personne est à l'origine de la conversation : elle ne peut pas en être retirée.",
                422,
            );
        }

        $conversation->participants()->detach($user->id);

        return ApiResponse::success([
            'conversation' => AdminConversationResource::make(
                $conversation->fresh()->load(['participants.roles', 'assignedAgent', 'firstMessage', 'messages.sender.roles', 'latestMessage.sender.roles'])
            ),
        ]);
    }

    /**
     * Forme commune d'une personne proposée à l'ajout : identité, rôle lisible,
     * et d'où vient la proposition.
     *
     * @return array<string, mixed>
     */
    private function personne(User $user, ?string $origine = null): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first(),
            // « Bien immobilier », « Nuitée »… : dit à l'agent POURQUOI cette
            // personne lui est proposée.
            'from_context' => $origine,
        ];
    }

    /**
     * Le vivier des agents habilités à répondre (sélecteur de réassignation).
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function assignableAgents(): array
    {
        return User::query()
            ->permission(AdminPermission::REPONDRE_MESSAGES->value)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $agent) => ['id' => $agent->id, 'name' => $agent->name])
            ->all();
    }

    /**
     * Identifiants du vivier (validation de la réassignation).
     *
     * @return array<int, int>
     */
    private function assignableAgentIds(): array
    {
        return array_column($this->assignableAgents(), 'id');
    }
}
