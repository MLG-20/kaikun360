<?php

namespace App\Modules\Build\Listeners;

use App\Modules\Build\Events\ConstructionQuoteSent;
use App\Modules\Build\Notifications\ConstructionQuoteSentNotification;

/**
 * À l'envoi d'un devis de chantier, prévient le client qui a déposé la demande.
 *
 * `client?->` : la relation est facultative en base (`nullOnDelete` en amont) —
 * un compte supprimé ne doit pas faire échouer l'envoi du devis, qui reste un
 * document valide du dossier.
 */
class NotifyClientOfConstructionQuote
{
    public function handle(ConstructionQuoteSent $event): void
    {
        $event->quote->constructionRequest->client?->notify(
            new ConstructionQuoteSentNotification($event->quote)
        );
    }
}
