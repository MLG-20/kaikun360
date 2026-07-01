<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `team_building_requests` — demandes de pack groupe entreprise (B9.1).
 *
 * Une entreprise décrit son besoin de team building (participants, lieu, dates,
 * budget, besoins). Kaikun compose ensuite un devis multi-prestataires (B9.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_building_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            // Entreprise à l'origine de la demande.
            $table->foreignId('company_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedInteger('participants');
            $table->string('city');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedBigInteger('budget_xof')->nullable();

            // Besoins structurés (ex. {"hebergement":true,"restauration":true,
            // "activite":true,"mobilite":true,"animation":false}).
            $table->json('needs')->nullable();
            $table->text('description')->nullable();

            $table->string('status')->default('nouveau')->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_building_requests');
    }
};
