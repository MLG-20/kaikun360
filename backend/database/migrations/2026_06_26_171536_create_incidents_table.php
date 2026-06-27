<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `incidents` — signalements liés à un bien (module Manage, B4.3).
 *
 * Un incident (panne, dégât, plainte…) est rattaché à un bien, avec une priorité
 * et un statut de traitement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            // Bien concerné.
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            // Auteur du signalement (facultatif).
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // Priorité indicative (p1 = critique … p4 = mineur).
            $table->string('priority')->nullable();

            // Statut de traitement (cf. enum IncidentStatus).
            $table->string('status')->default('ouvert')->index();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
