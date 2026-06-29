<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `mobility_services` — trajets de mobilité programmés (module Mobility, B7.2).
 *
 * Un service relie un départ à une destination, avec une capacité et un tarif
 * par place. Peut s'appuyer sur un véhicule du prestataire (`vehicle_id`).
 * Recherchable par type / ville / date une fois validé (B7.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobility_services', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
            // Véhicule affecté au service (facultatif).
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();

            // Type (cf. enum MobilityServiceType) et itinéraire.
            $table->string('type')->index();
            $table->string('departure');   // lieu/ville de départ
            $table->string('destination'); // lieu/ville d'arrivée
            // Départ programmé (facultatif pour un service à la demande).
            $table->timestamp('departure_at')->nullable()->index();

            $table->unsignedInteger('capacity')->default(1);
            $table->unsignedBigInteger('price_xof'); // par place
            $table->text('description')->nullable();

            // Statut de modération + traçabilité de validation.
            $table->string('status')->default('en_attente_validation')->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobility_services');
    }
};
