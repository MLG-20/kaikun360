<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `construction_milestones` — jalons de chantier (module Build, B5.3).
 *
 * Découpe une demande de construction en étapes ordonnées (fondations, gros
 * œuvre, finitions…), chacune avec son statut et ses dates prévisionnelle/réelle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_milestones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('construction_request_id')
                ->constrained('construction_requests')
                ->cascadeOnDelete();

            $table->string('name');
            // Ordre d'affichage / d'exécution de l'étape.
            $table->unsignedInteger('position')->default(0);

            // Statut (cf. enum MilestoneStatus).
            $table->string('status')->default('a_venir')->index();

            // Dates prévisionnelle et réelle d'achèvement.
            $table->date('planned_date')->nullable();
            $table->date('actual_date')->nullable();

            $table->timestamps();

            $table->index(['construction_request_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_milestones');
    }
};
