<?php

namespace App\Modules\Core\Http\Resources;

use App\Support\Trash\PersonalHiding;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Représentation JSON d'une notification "base de données" pour l'espace client (F3.6).
 *
 * Elle projette la ligne de la table `notifications` (canal `database` de Laravel)
 * dans un format stable et directement exploitable par le frontend :
 *   - la charge utile applicative (titre, message, lien, catégorie) est lue dans
 *     la colonne JSON `data`, alimentée par la méthode `toArray()` de chaque
 *     Notification (RequestStatusChanged…, QuoteReceived…, BookingConfirmed…) ;
 *   - l'état "lu / non lu" est dérivé de `read_at` en un booléen simple `read`.
 *
 * Les valeurs par défaut (`??`) garantissent un rendu robuste même si une
 * notification ancienne ou externe n'a pas rempli tous les champs attendus.
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->data ?? [];

        return [
            'id' => $this->id,
            'category' => $data['category'] ?? 'general',
            'title' => $data['title'] ?? 'Notification',
            'body' => $data['body'] ?? '',
            // Lien interne (route frontend) vers l'écran concerné ; peut être null.
            'action_url' => $data['action_url'] ?? null,
            // État de lecture exposé en booléen : plus simple à consommer côté Angular
            // que l'horodatage brut (que l'on conserve tout de même ci-dessous).
            'read' => $this->read_at !== null,
            // F11.5 — peut-elle être rangée dans la corbeille ? Miroir exact de
            // `PersonalHiding` : on ne range qu'une notification DÉJÀ LUE, la
            // masquer avant de l'avoir ouverte effacerait l'information sans
            // l'avoir reçue.
            'hideable' => $request->user()
                ? (new PersonalHiding)->estRangeable($this->resource, $request->user())
                : false,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
