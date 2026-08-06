<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `partner_dues` — LE REGISTRE de ce que Kaikun doit à ses partenaires
 * (F8.16.a). Une ligne = une dette née d'un service rendu.
 *
 * ⚠️ **Pourquoi cette table n'existait pas alors que l'argent circulait.**
 * Kaikun encaisse et commissionne sur tous les univers depuis F8.4, mais ne
 * reversait qu'en gestion locative : `owner_payouts.mandate_id` est **non
 * nullable** et pointe sur `management_mandates` — la table est structurellement
 * incapable de porter un reversement d'hôte, de loueur ou d'organisateur. Sa
 * propre migration annonçait un « ledger B11/B14 » qui n'a jamais été écrit.
 * Conséquence concrète : **si un hôte demandait ce que Kaikun lui devait,
 * personne ne pouvait répondre.**
 *
 * ⚠️ **Une seule table, pas une par univers.** Vérifié dans les modèles : il n'y
 * a que **deux natures de bénéficiaire** (propriétaire d'un bien, prestataire) et
 * toutes deux sont des **`users`** — `Vehicle.provider_id`,
 * `MobilityService.provider_id` et `TourismExperience.provider_id` référencent
 * `users`, pas `providers`. D'où une simple colonne `beneficiary_id`.
 *
 * ⚠️ **La source est POLYMORPHE**, comme `bookings.bookable` :
 *   - `Booking` — nuitée, véhicule, circuit, trajet ;
 *   - `ProviderMission` — team building et construction, dont le devis est un
 *     total « coûts + marge » qui ne dit RIEN de ce qui revient à chacun. Ce qui
 *     est dû y vit mission par mission (un séminaire peut devoir de l'argent à
 *     quatre prestataires). Reverser depuis le devis serait faux.
 *
 * ⚠️ **La caution n'entre pas dans l'assiette** : elle est retenue puis
 * restituée ou saisie, elle n'a jamais appartenu au partenaire. `gross_xof`
 * copie `amount_xof`, jamais `caution_xof`.
 *
 * ⚠️ **La commission est RECOPIÉE, pas recalculée.** Elle est figée sur la
 * source depuis F8.4 ; la relire au moment du reversement ferait dépendre une
 * dette passée du barème d'aujourd'hui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_dues', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            // Le bénéficiaire est TOUJOURS un utilisateur (propriétaire ou
            // prestataire) : aucune indirection par univers.
            $table->foreignId('beneficiary_id')->constrained('users')->cascadeOnDelete();

            // Source de la dette : Booking ou ProviderMission. Colonnes posées à
            // la main plutôt que par `morphs()` : l'index composé dont on a
            // besoin est l'UNIQUE défini plus bas, et `morphs()` en ajouterait un
            // second, redondant, sur les deux mêmes colonnes.
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');

            // Assiette (hors caution), commission figée recopiée, et net dû.
            // `net_xof` est stocké et non calculé à la volée : c'est le montant
            // qu'on a annoncé au partenaire, il doit survivre à tout.
            $table->unsignedBigInteger('gross_xof');
            $table->unsignedBigInteger('commission_xof')->default(0);
            $table->unsignedBigInteger('net_xof');

            $table->string('status')->default('en_attente')->index();

            // Date à partir de laquelle la dette est payable : service rendu +
            // délai de sûreté. Calculée sur la date de FIN DE SERVICE et non sur
            // l'instant du calcul — sinon un traitement lancé en retard
            // repousserait le paiement du partenaire sans raison.
            $table->timestamp('eligible_at')->nullable()->index();

            // Lot de paiement qui a soldé cette dette (null tant qu'impayée).
            $table->foreignId('payout_id')->nullable()->constrained('partner_payouts')->nullOnDelete();

            // Pourquoi une dette a été annulée (réservation remboursée…).
            $table->string('cancelled_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            // ⚠️ Une source ne peut engendrer qu'UNE dette : sans cette
            // contrainte, deux passages du calcul paieraient deux fois le même
            // service. L'idempotence est garantie par la base, pas seulement par
            // le code qui l'interroge.
            $table->unique(['source_type', 'source_id']);

            $table->index(['beneficiary_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_dues');
    }
};
