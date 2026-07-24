<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Pro\Models\Provider;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy des avis (couche transversale, B12.2).
 *
 * Règle centrale du cahier des charges : un avis ne peut être laissé que par un
 * utilisateur ayant **réellement consommé** le service/bien concerné (preuve =
 * réservation terminée). La modération (publier/rejeter) est réservée aux
 * agents/admin.
 */
class ReviewPolicy
{
    /**
     * Déposer un avis : il faut avoir réellement consommé la cible notée.
     *
     * L'éligibilité dépend de la nature de la cible :
     *   - un **prestataire** (F5.5) se note après une **mission terminée** ;
     *   - toute autre ressource (nuitée, véhicule, expérience) se note après une
     *     **réservation terminée**.
     */
    public function create(User $user, Model $reviewable): bool
    {
        if ($reviewable instanceof Provider) {
            return Review::hasCompletedMissionWith($user, $reviewable);
        }

        return Review::hasConsumed($user, $reviewable);
    }

    /**
     * Modérer un avis (publier/rejeter) : agents/admin (B12.3).
     */
    public function moderate(User $user, Review $review): bool
    {
        return $user->hasAnyRole([UserRole::AGENT_KAIKUN->value, UserRole::ADMIN->value]);
    }
}
