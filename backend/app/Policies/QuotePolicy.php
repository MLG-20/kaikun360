<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;

/**
 * Policy des devis génériques (couche transversale, B11.3).
 *
 * Un devis se consulte par le demandeur (propriétaire de la demande) ou un
 * agent/admin ; seul le demandeur peut l'accepter/refuser.
 */
class QuotePolicy
{
    public function view(User $user, Quote $quote): bool
    {
        return $user->id === $quote->request->user_id
            || $user->hasAnyRole([UserRole::AGENT_KAIKUN->value, UserRole::ADMIN->value]);
    }

    /**
     * Répondre (accepter/refuser) : le demandeur uniquement.
     */
    public function respond(User $user, Quote $quote): bool
    {
        return $user->id === $quote->request->user_id;
    }
}
