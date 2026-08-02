<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Quote;
use App\Support\Billing\CommissionCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Conversion d'un devis accepté en réservation payable (F8.11).
 *
 * LE TROU QUE CETTE CLASSE COMBLE
 * -------------------------------
 * Deux couches avaient été construites séparément et n'avaient jamais été
 * reliées :
 *   - la DEMANDE (`ServiceRequest`) et son DEVIS (`Quote`) — un prospect, un
 *     chiffrage, un accord ;
 *   - la RÉSERVATION (`Booking`) — le seul objet qu'un paiement sache régler,
 *     `POST /payments/initiate` exigeant un `booking_id`.
 *
 * Entre les deux, rien. Accepter un devis se contentait de changer la colonne
 * `quotes.status` : aucun montant ne devenait exigible, aucun écran de règlement
 * n'était atteignable. Le client disait « oui » et le circuit s'arrêtait là.
 *
 * LE CHOIX DE MODÉLISATION
 * ------------------------
 * Le devis accepté devient lui-même la CIBLE polymorphe de la réservation
 * (`bookable_type = Quote`). La table `bookings` est polymorphe depuis B3.3 :
 * aucune migration n'a été nécessaire. Et le sens est juste — sur du sur-mesure,
 * il n'existe aucune fiche au catalogue à désigner : ce qui est vendu, c'est
 * exactement le devis, avec son montant et ses lignes.
 *
 * CE QUI N'EST **PAS** FAIT ICI
 * -----------------------------
 * Aucune notification n'est envoyée depuis ce service : il est appelé dans une
 * transaction, et un e-mail parti avant un `rollback` annoncerait au client une
 * réservation qui n'existe pas. Les notifications sont l'affaire de l'appelant,
 * une fois la transaction validée.
 */
class QuoteConversionService
{
    public function __construct(private readonly CommissionCalculator $commissions)
    {
    }

    /**
     * Crée la réservation exigible correspondant à un devis accepté.
     *
     * IDEMPOTENT : si le devis porte déjà une réservation, on rend celle-ci sans
     * rien créer. C'est indispensable — un double clic du client, un rejeu de
     * requête ou une relance de l'agent ne doivent jamais produire deux
     * réservations, donc deux montants à payer pour une seule prestation.
     *
     * @param Quote $quote Devis que le client vient d'accepter.
     */
    public function convert(Quote $quote): Booking
    {
        return DB::transaction(function () use ($quote) {
            // Verrou de ligne : deux acceptations concurrentes du même devis se
            // sérialisent ici, la seconde retrouvera la réservation de la
            // première au lieu d'en créer une jumelle.
            $quote = Quote::query()->lockForUpdate()->findOrFail($quote->id);

            if ($existing = $quote->booking()->first()) {
                return $existing;
            }

            $montant = (int) $quote->amount_xof;

            return $quote->booking()->create([
                'reference' => 'BK-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                // Le titulaire est le DEMANDEUR, jamais l'agent qui a chiffré.
                'user_id' => $quote->request->user_id,
                // Un devis sur-mesure n'a ni période ni nombre de participants :
                // ces colonnes restent nulles, `bookings` les autorise.
                'amount_xof' => $montant,
                // Commission figée à la conversion, au taux du moment — même
                // régime que tous les autres univers depuis F8.4.
                'commission_xof' => $this->commissions->commissionFor($montant),
                // En attente de règlement : c'est l'encaissement (IPN PayTech ou
                // confirmation manuelle) qui la fera passer à « confirmée ».
                'status' => BookingStatus::EN_ATTENTE->value,
            ]);
        });
    }
}
