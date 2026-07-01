<?php

namespace App\Modules\TeamBuilding\Events;

use App\Modules\TeamBuilding\Models\TeamBuildingQuote;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis lorsqu'un devis team building est envoyé à l'entreprise.
 *
 * Notifie l'entreprise (listener NotifyCompanyOfQuoteSent).
 */
class QuoteSent
{
    use Dispatchable;

    public function __construct(public TeamBuildingQuote $quote) {}
}
