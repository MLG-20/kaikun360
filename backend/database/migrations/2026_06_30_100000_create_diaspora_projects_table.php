<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `diaspora_projects` — projets pilotés à distance (module Diaspora, B8.1).
 *
 * Un client de la diaspora dépose un projet (achat, construction, gestion
 * locative) depuis son pays de résidence ; un agent dédié lui est affecté
 * (`agent_id`). La priorité distingue les dossiers à forte valeur. Les rapports
 * de suivi réutilisent le modèle transversal `App\Models\Report` (polymorphe).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diaspora_projects', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            // Client (diaspora) et agent dédié (affecté ensuite, donc nullable).
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();

            // Nature du projet (cf. enum DiasporaProjectType) et pays de résidence.
            $table->string('project_type');
            $table->string('residence_country');
            $table->unsignedBigInteger('budget_xof')->nullable();
            $table->text('description')->nullable();

            // Priorité (back-office) et statut d'avancement.
            $table->string('priority')->default('normale')->index();
            $table->string('status')->default('nouveau')->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diaspora_projects');
    }
};
