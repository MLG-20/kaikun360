<?php

namespace App\Support\Payments;

/**
 * Résultat d'une initiation de paiement (B14).
 *
 * Renvoyé par {@see PaymentProviderInterface::initiate()} : la référence du PSP
 * et l'URL vers laquelle rediriger le client pour régler.
 */
readonly class PaymentIntent
{
    public function __construct(
        public string $providerReference,
        public string $redirectUrl,
    ) {
    }
}
