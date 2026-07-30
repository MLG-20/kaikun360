<?php

namespace App\Modules\Build\Events;

use App\Modules\Build\Models\ConstructionQuote;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis lorsqu'un devis de chantier est envoyé au client (F3.9).
 *
 * Prévient le client (listener `NotifyClientOfConstructionQuote`). Sans cet
 * événement, l'envoi d'un devis ne se voyait nulle part : le statut basculait en
 * base et le client devait deviner qu'un chiffrage l'attendait.
 */
class ConstructionQuoteSent
{
    use Dispatchable;

    public function __construct(public ConstructionQuote $quote) {}
}
