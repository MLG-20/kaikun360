<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `properties` — biens immobiliers (module Immo, phase B2).
 *
 * Un bien est déposé par un propriétaire et n'est visible publiquement
 * qu'une fois validé par un agent (statut par défaut : en_attente_validation).
 * Les colonnes de localisation et de filtrage sont indexées pour des
 * recherches de catalogue rapides (cf. B17).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();

            // Propriétaire du bien (utilisateur ayant le rôle proprietaire).
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();

            // Nature du bien (cf. enum App\Modules\Immo\Enums\PropertyType).
            $table->string('type')->index();

            $table->string('title');
            $table->text('description')->nullable();

            // Prix en francs CFA (XOF), entier. Nullable : "prix sur demande".
            $table->unsignedBigInteger('price_xof')->nullable();

            // Localisation structurée via le référentiel officiel
            // (regions / departments / communes). Nullable pour ne pas bloquer un
            // import partiel, mais requis au dépôt (cf. Form Request en B2.3).
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('commune_id')->nullable()->constrained()->nullOnDelete();

            // Zone touristique (filtre dédié) + adresse précise (quartier, rue) + GPS.
            $table->string('tourist_zone')->nullable()->index();
            $table->text('address')->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();

            // Statut métier (cf. enum PropertyStatus). Par défaut : en attente de validation.
            $table->string('status')->default('en_attente_validation')->index();

            // Niveau de vérification (terrain / documents / renforcé).
            $table->string('verification_level')->default('unverified')->index();

            // Traçabilité de la validation.
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            // Index composés pour les filtres fréquents du catalogue.
            // (region_id, department_id, commune_id sont déjà indexés par les FK.)
            $table->index(['status', 'type']);
            $table->index('price_xof');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
