<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Resources\NotificationResource;
use App\Support\ApiResponse;
use App\Support\Trash\PersonalHiding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Notifications "base de données" de l'utilisateur connecté (phase F3.6).
 *
 * Ce contrôleur alimente l'écran « Mes notifications » de l'espace client.
 * Il s'appuie sur le trait `Notifiable` du modèle User (canal `database`), qui
 * expose les relations `notifications()` / `unreadNotifications()` sur la table
 * `notifications`.
 *
 * Règle de sécurité constante : on ne manipule QUE les notifications de
 * l'utilisateur courant (`$request->user()->notifications()`), jamais un accès
 * direct au modèle global — impossible de lire ou marquer la notification d'un
 * autre utilisateur, même en devinant son UUID.
 */
class NotificationController extends Controller
{
    /**
     * Liste paginée de mes notifications, les plus récentes d'abord.
     * GET /api/v1/users/me/notifications
     *
     * On joint `unread_count` aux métadonnées : le frontend affiche ainsi la
     * pastille "non lues" sans second appel réseau.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $notifications = $user->notifications()
            // F11.5 — les notifications rangées quittent la liste. ⚠️ Le
            // compteur de non-lues, lui, n'est PAS filtré : par construction on
            // ne range qu'une notification déjà LUE, donc aucune non-lue ne peut
            // être masquée — et si la règle changeait un jour, mieux vaut que la
            // pastille continue de dire la vérité plutôt que de se taire.
            ->whereNull('hidden_at')
            ->paginate(15);

        return NotificationResource::collection($notifications)
            ->additional(['unread_count' => $user->unreadNotifications()->count()]);
    }

    /**
     * Nombre de notifications non lues (pour la pastille de l'en-tête).
     * GET /api/v1/users/me/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Marque UNE notification comme lue.
     * PATCH /api/v1/users/me/notifications/{notification}/read
     *
     * `findOrFail` est appelé sur la relation de l'utilisateur : une notification
     * inexistante OU appartenant à autrui renvoie un 404 (jamais de fuite).
     * Opération idempotente : re-marquer une notification déjà lue ne fait rien.
     */
    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()->findOrFail($notification);

        $item->markAsRead();

        return ApiResponse::success([
            'notification' => NotificationResource::make($item),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Range UNE notification dans ma corbeille (F11.5).
     * POST /api/v1/users/me/notifications/{notification}/hide
     *
     * ⚠️ **Ne supprime rien** : écrit `hidden_at`, honorée par la seule liste
     * ci-dessus. Une notification est la trace d'un événement — la garder
     * permet de retrouver « quand ai-je été prévenu ? », question qui se pose
     * exactement le jour d'un désaccord.
     *
     * ⚠️ On ne range qu'une notification **déjà lue** : la masquer avant de
     * l'avoir ouverte reviendrait à effacer l'information sans l'avoir reçue.
     */
    public function hide(Request $request, string $notification, PersonalHiding $corbeille): JsonResponse
    {
        // `findOrFail` sur la relation de l'utilisateur : une notification
        // inexistante OU appartenant à autrui renvoie 404 (jamais de fuite).
        $item = $request->user()->notifications()->findOrFail($notification);

        if ($raison = $corbeille->raisonDeBlocage($item, $request->user())) {
            return ApiResponse::error($raison, 422);
        }

        $corbeille->masquer($item, $request->user());

        return ApiResponse::success([
            'message' => 'Notification rangée dans votre corbeille. Rien n’est supprimé.',
        ]);
    }

    /**
     * Marque TOUTES mes notifications non lues comme lues.
     * PATCH /api/v1/users/me/notifications/read-all
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return ApiResponse::success([
            'message' => 'Toutes les notifications ont été marquées comme lues.',
            'unread_count' => 0,
        ]);
    }
}
