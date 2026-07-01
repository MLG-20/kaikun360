<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `team_building_quotes` — devis composés multi-prestataires (B9.2).
 *
 * Un devis agrège des lignes de plusieurs modules (lieu, hébergement,
 * restauration, activité, mobilité, animation). Les lignes sont stockées en JSON ;
 * les totaux (sous-total, marge, total) sont figés à la composition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_building_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            $table->foreignId('request_id')
                ->constrained('team_building_requests')
                ->cascadeOnDelete();

            // Lignes du devis (catégorie, libellé, module, quantité, prix unitaire, montant).
            $table->json('lines');

            // Totaux figés à la composition.
            $table->unsignedBigInteger('subtotal_xof')->default(0);
            $table->decimal('margin_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('margin_xof')->default(0);
            $table->unsignedBigInteger('total_xof')->default(0);

            // Statut (cf. enum TeamBuildingQuoteStatus) + horodatages métier.
            $table->string('status')->default('brouillon')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_building_quotes');
    }
};
