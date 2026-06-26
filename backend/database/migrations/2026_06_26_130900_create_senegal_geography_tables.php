<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel géographique du Sénégal : régions, départements et communes.
 *
 * Données de référence partagées par plusieurs modules (Immo, Stay, Mobility…),
 * d'où leur emplacement transverse (modèles dans app/Models).
 *  - régions + départements : SenegalGeographySeeder (14 régions, 46 départements) ;
 *  - communes (~557) : CommunesSeeder, qui importe database/data/communes.json
 *    (à compléter depuis la source officielle ANSD).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();   // ex. "Dakar", "Ziguinchor"
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            // Un même nom de département est unique au sein d'une région.
            $table->unique(['region_id', 'name']);
        });

        Schema::create('communes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Type éventuel : "commune" ou "commune d'arrondissement" (Dakar, Pikine…).
            $table->string('type')->nullable();
            $table->timestamps();

            // Un même nom de commune est unique au sein d'un département.
            $table->unique(['department_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communes');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('regions');
    }
};
