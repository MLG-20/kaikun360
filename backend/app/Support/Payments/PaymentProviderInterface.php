<?php

namespace App\Support\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;

/**
 * Contrat d'un fournisseur de paiement (B14).
 *
 * TOUT le code métier (Bookings, Quotes, Mobility, Explore…) dépend de cette
 * abstraction, jamais d'un PSP concret. PayTech en est aujourd'hui la seule
 * implémentation ({@see PaytechProvider}), mais l'interface garantit qu'on
 * pourra en changer sans toucher aux modules.
 */
interface PaymentProviderInterface
{
    /**
     * Crée l'intention de paiement côté PSP et renvoie l'URL de redirection.
     *
     * @param  array<string, mixed>  $context  Données optionnelles (client, retour…).
     */
    public function initiate(Payment $payment, array $context = []): PaymentIntent;

    /**
     * Confirme (capture) un paiement autorisé, si le moyen l'exige. Renvoie le
     * statut résultant.
     */
    public function confirm(Payment $payment): PaymentStatus;

    /**
     * Rembourse tout ou partie d'un paiement (caution, annulation éligible).
     *
     * @param  int|null  $amountXof  Montant à rembourser ; null = total.
     */
    public function refund(Payment $payment, ?int $amountXof = null): bool;

    /**
     * Interroge le PSP sur le statut courant, ou null s'il est indéterminé.
     */
    public function status(Payment $payment): ?PaymentStatus;
}
