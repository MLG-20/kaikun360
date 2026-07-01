<?php

namespace App\Modules\TeamBuilding\Listeners;

use App\Modules\TeamBuilding\Events\QuoteSent;
use App\Modules\TeamBuilding\Notifications\TeamBuildingQuoteSentNotification;

/**
 * À l'envoi d'un devis, prévient l'entreprise à l'origine de la demande.
 */
class NotifyCompanyOfQuoteSent
{
    public function handle(QuoteSent $event): void
    {
        $event->quote->request->company?->notify(
            new TeamBuildingQuoteSentNotification($event->quote)
        );
    }
}
