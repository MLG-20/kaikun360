<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
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
     * Déposer un avis : il faut avoir consommé la ressource notée.
     */
    public function create(User $user, Model $reviewable): bool
    {
        return Review::hasConsumed($user, $reviewable);
    }

    /**
     * Modérer un avis (publier/rejeter) : agents/admin (B12.3).
     */
    public function moderate(User $user): bool
    {
        return $user->hasAnyRole([UserRole::AGENT_KAIKUN->value, UserRole::ADMIN->value]);
    }
}
