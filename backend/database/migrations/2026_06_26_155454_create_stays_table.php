<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `stays` — configuration "nuitées / courte durée" d'un bien (module Stay, B3).
 *
 * Relation 1–1 avec un bien (`properties`) : un bien peut être proposé en
 * location à la nuitée via une configuration Stay (prix/nuit, caution, capacité,
 * règles). La visibilité publique dépend du bien (publié) ET de `is_active`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stays', function (Blueprint $table) {
            $table->id();

            // Un bien = au plus une configuration nuitées.
            $table->foreignId('property_id')->unique()->constrained()->cascadeOnDelete();

            // Tarification (en francs CFA).
            $table->unsignedBigInteger('price_per_night_xof');
            $table->unsignedBigInteger('caution_xof')->default(0); // caution / dépôt de garantie

            // Capacité et contraintes de séjour.
            $table->unsignedInteger('capacity')->default(1);   // nombre de personnes
            $table->unsignedInteger('min_nights')->default(1);
            $table->unsignedInteger('max_nights')->nullable();

            // Données structurées (règles de la maison, équipements).
            $table->json('rules')->nullable();
            $table->json('amenities')->nullable();

            // Horaires d'arrivée/départ.
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();

            // Activation par le propriétaire (indépendante du statut du bien).
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stays');
    }
};
