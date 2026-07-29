<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `construction_quotes` — devis de chantier ventilés par lot (F7.3.e2).
 *
 * Le CDC §6 *Construction* attend des « demandes de devis » : jusqu'ici la
 * plateforme n'affichait qu'un COÛT ESTIMÉ, produit par le simulateur. Un devis
 * est autre chose — un engagement chiffré, ventilé par corps d'état, envoyé au
 * client puis accepté ou refusé.
 *
 * ⚠️ La table transversale `quotes` (B11.3) ne pouvait pas servir : elle pend sur
 * `requests` (les demandes de contact génériques) et ne porte qu'un montant
 * global. Ici on reprend le motif éprouvé des devis pack du team building
 * (`team_building_quotes`, B9.2) : lignes en JSON, totaux FIGÉS à la composition.
 *
 * Les totaux sont figés (et non recalculés à la lecture) parce qu'un devis envoyé
 * ne doit plus bouger : si le barème ou la marge change ensuite, le document déjà
 * transmis au client reste celui qu'il a reçu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            $table->foreignId('construction_request_id')
                ->constrained('construction_requests')
                ->cascadeOnDelete();

            // Lignes du devis : {lot, label, quantity, unit, unit_price_xof, amount_xof}.
            $table->json('lines');

            // Totaux figés à la composition.
            $table->unsignedBigInteger('subtotal_xof')->default(0);
            $table->decimal('margin_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('margin_xof')->default(0);
            $table->unsignedBigInteger('total_xof')->default(0);

            // Validité commerciale de l'offre (un devis BTP se périme).
            $table->date('valid_until')->nullable();

            // Statut (cf. enum ConstructionQuoteStatus) + horodatages métier.
            $table->string('status')->default('brouillon')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();

            // Auteur de la composition (traçabilité : qui a chiffré ce chantier).
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_quotes');
    }
};
