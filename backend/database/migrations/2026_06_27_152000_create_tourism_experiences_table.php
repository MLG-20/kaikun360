<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `tourism_experiences` — circuits & expériences touristiques (module Explore, B6.1).
 *
 * Publiée par un prestataire (validé), proposée au catalogue après validation
 * par un agent. La capacité borne le nombre total de places du circuit ; les
 * inclusions (restauration, guide, transport…) sont stockées en JSON structuré.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tourism_experiences', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            // Prestataire à l'origine de l'expérience.
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();

            $table->string('title');
            $table->string('destination');
            $table->text('description')->nullable();

            // Durée du circuit (jours) et tarif par personne.
            $table->unsignedInteger('duration_days')->default(1);
            $table->unsignedBigInteger('price_xof');

            // Capacité totale (places) du circuit.
            $table->unsignedInteger('capacity')->default(1);

            // Inclusions structurées (ex. {"restauration": true, "guide": true}).
            $table->json('inclusions')->nullable();

            // Statut de modération (cf. enum ExperienceStatus) + traçabilité de validation.
            $table->string('status')->default('en_attente_validation')->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tourism_experiences');
    }
};
