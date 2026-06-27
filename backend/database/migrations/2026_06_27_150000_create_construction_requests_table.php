<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `construction_requests` — demandes de construction/rénovation (module Build, B5.1).
 *
 * Un client décrit son projet (objectif, ville, surface, budget, niveau de
 * finition) ; Kaikun étudie la demande, propose un devis (B11) et suit le
 * chantier (rapports B5.2, jalons B5.3). `estimated_cost_xof` est l'estimation
 * indicative produite par le simulateur (B5.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            // Client à l'origine de la demande.
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();

            // Nature du projet (cf. enum ConstructionObjective).
            $table->string('objective');

            // Localisation et caractéristiques.
            $table->string('city');
            $table->unsignedInteger('surface_m2');
            // Budget annoncé par le client (facultatif).
            $table->unsignedBigInteger('budget_xof')->nullable();
            // Niveau de finition souhaité (cf. enum FinishLevel).
            $table->string('finish_level');

            $table->text('description')->nullable();

            // Estimation indicative calculée par le simulateur (B5.4).
            $table->unsignedBigInteger('estimated_cost_xof')->nullable();

            // Statut d'avancement (cf. enum ConstructionRequestStatus).
            $table->string('status')->default('soumise')->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_requests');
    }
};
