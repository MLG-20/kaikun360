<?php

namespace App\Modules\TeamBuilding\Listeners;

use App\Modules\TeamBuilding\Events\QuoteAccepted;

/**
 * À l'acceptation d'un devis, amorce le suivi opérationnel multi-prestataires.
 *
 * Pour l'instant on trace l'amorçage (audit) ; l'orchestration concrète des
 * prestataires (réservations fermes, échéancier) s'appuiera sur la couche
 * transversale Bookings/Quotes (B11).
 */
class StartOperationalFollowUp
{
    public function handle(QuoteAccepted $event): void
    {
        activity()
            ->performedOn($event->quote)
            ->withProperties(['request_id' => $event->quote->request_id])
            ->log('Suivi opérationnel team building amorcé');
    }
}
