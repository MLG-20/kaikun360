<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `vehicles` — véhicules & moyens de transport (module Mobility, B7.1).
 *
 * Publié par un prestataire, validé par un agent avant d'apparaître dans la
 * recherche. Les champs de CONFORMITÉ diffèrent selon la catégorie : transport
 * motorisé (assurance, identité chauffeur) vs pirogue (gilets, météo,
 * conformité prestataire) — contrôlés à la validation (B7.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            // Prestataire propriétaire du véhicule.
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();

            // Catégorie (cf. enum VehicleType) + descriptif commercial.
            $table->string('type')->index();
            $table->string('brand')->nullable();        // marque
            $table->string('model')->nullable();
            $table->unsignedInteger('capacity')->default(1);
            $table->unsignedBigInteger('price_per_day_xof');
            $table->boolean('has_driver')->default(false); // chauffeur inclus
            $table->unsignedBigInteger('caution_xof')->default(0);
            $table->text('description')->nullable();

            // --- Conformité transport motorisé ---
            $table->string('insurance_ref')->nullable();   // assurance
            $table->string('driver_identity')->nullable(); // identité du chauffeur

            // --- Conformité pirogue ---
            $table->unsignedInteger('life_jackets_count')->nullable(); // gilets de sauvetage
            $table->boolean('weather_compliant')->nullable();          // conditions météo vérifiées
            $table->boolean('provider_compliant')->nullable();         // conformité prestataire

            // Statut de modération + traçabilité de validation.
            $table->string('status')->default('en_attente_validation')->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
