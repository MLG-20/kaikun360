<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Quote;
use App\Modules\Build\Models\ConstructionQuote;
use App\Modules\TeamBuilding\Models\TeamBuildingQuote;
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

    /**
     * Même conversion pour un devis de **chantier** (F8.14).
     *
     * Troisième famille de devis du produit, et troisième fois le même trou :
     * `ConstructionQuoteController::accept()` changeait deux colonnes `status`
     * et rendait la main. Le client — celui de F3.9, qui répond enfin à ses
     * devis de chantier depuis son espace — disait « oui » à un devis de
     * plusieurs millions sans que rien ne devienne payable.
     *
     * ⚠️ Comme en team building, la commission est la **marge déjà chiffrée**
     * (`margin_xof`) : le devis est ventilé par lot, coûts puis marge, et c'est
     * le total qui est signé. Appliquer par-dessus la commission commune
     * facturerait deux fois la même rémunération.
     *
     * Un chantier n'a ni dates de séjour ni participants : ces colonnes restent
     * nulles, `bookings` les autorise.
     */
    public function convertConstruction(ConstructionQuote $quote): Booking
    {
        return DB::transaction(function () use ($quote) {
            $quote = ConstructionQuote::query()->lockForUpdate()->findOrFail($quote->id);

            if ($existing = $quote->booking()->first()) {
                return $existing;
            }

            return $quote->booking()->create([
                'reference' => 'BK-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'user_id' => $quote->constructionRequest->client_id,
                'amount_xof' => (int) $quote->total_xof,
                'commission_xof' => (int) $quote->margin_xof,
                'status' => BookingStatus::EN_ATTENTE->value,
            ]);
        });
    }

    /**
     * Même conversion pour un devis de **team building** (F8.14).
     *
     * POURQUOI UNE SECONDE MÉTHODE ET NON UN CAS DU `convert()` CI-DESSUS
     * ------------------------------------------------------------------
     * Le module Team building a ses PROPRES devis (`team_building_quotes`,
     * B9.2), composés ligne à ligne à partir de plusieurs prestataires — ils ne
     * partagent avec `quotes` ni la table, ni les colonnes, ni surtout la façon
     * dont Kaikun s'y rémunère (voir ci-dessous). Les fondre dans une abstraction
     * commune masquerait cette différence au lieu de la dire.
     *
     * C'est ce module qui avait été oublié : `TeamBuildingQuoteController::accept()`
     * changeait deux colonnes `status` et déclenchait un écouteur qui écrivait une
     * ligne d'audit en annonçant que « l'orchestration s'appuiera sur la couche
     * Bookings/Quotes » — ce qui n'avait jamais été fait. Une entreprise pouvait
     * donc demander un séminaire, recevoir un devis, l'accepter… et n'avoir rien à
     * payer, faute de réservation. Le trou exact que F8.11 avait bouché à côté.
     *
     * ⚠️ LA COMMISSION EST LA **MARGE DÉJÀ CHIFFRÉE DANS LE DEVIS**, pas un
     * pourcentage appliqué par-dessus. Un devis team building est composé des
     * coûts prestataires (`subtotal_xof`) plus la marge de la plateforme
     * (`margin_xof`, 15 % par défaut), et c'est le TOTAL qui est présenté et
     * accepté par l'entreprise. Y ajouter la commission commune de `CommissionCalculator`
     * facturerait deux fois la même rémunération — et sur un montant que le client
     * a déjà signé.
     *
     * IDEMPOTENT, comme `convert()` : verrou de ligne + `morphOne`.
     */
    public function convertTeamBuilding(TeamBuildingQuote $quote): Booking
    {
        return DB::transaction(function () use ($quote) {
            $quote = TeamBuildingQuote::query()->lockForUpdate()->findOrFail($quote->id);

            if ($existing = $quote->booking()->first()) {
                return $existing;
            }

            $demande = $quote->request;

            return $quote->booking()->create([
                'reference' => 'BK-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                // Le titulaire est l'ENTREPRISE qui a déposé la demande.
                'user_id' => $demande->company_id,
                // À la différence du sur-mesure générique, un séminaire a des
                // dates et un effectif : la réservation les porte, ce qui la rend
                // lisible dans une liste sans rouvrir le devis.
                'start_date' => $demande->start_date,
                'end_date' => $demande->end_date,
                'guests' => $demande->participants,
                'amount_xof' => (int) $quote->total_xof,
                'commission_xof' => (int) $quote->margin_xof,
                'status' => BookingStatus::EN_ATTENTE->value,
            ]);
        });
    }
}
