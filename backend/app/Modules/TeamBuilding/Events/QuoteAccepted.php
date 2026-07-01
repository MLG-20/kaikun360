<?php

namespace App\Modules\TeamBuilding\Events;

use App\Modules\TeamBuilding\Models\TeamBuildingQuote;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis lorsqu'une entreprise accepte un devis team building.
 *
 * Déclenche le suivi opérationnel multi-prestataires (listener
 * StartOperationalFollowUp).
 */
class QuoteAccepted
{
    use Dispatchable;

    public function __construct(public TeamBuildingQuote $quote) {}
}
