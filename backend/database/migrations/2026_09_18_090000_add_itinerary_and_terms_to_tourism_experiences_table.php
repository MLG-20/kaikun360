<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Évolution de `tourism_experiences` (F21) : la capacité et les inclusions à
 * cocher étaient insuffisantes pour un vrai circuit.
 *
 * - `capacity` disparaît : la capacité vit désormais PAR DATE DE DÉPART, dans
 *   `tourism_experience_departures` (une session pleine n'empêche pas une
 *   autre date du même circuit d'accepter du monde).
 * - `inclusions` (4 cases fixes) est remplacé par `included`/`excluded`, en
 *   texte libre — un circuit promet des choses trop variées pour 4 cases.
 * - `itinerary` (nouveau) porte le programme jour par jour.
 *
 * Migration sans risque de perte : au moment où elle est écrite, aucun
 * circuit n'existe encore en production (back-office en lecture seule
 * jusqu'ici), il n'y a donc rien à convertir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tourism_experiences', function (Blueprint $table) {
            $table->json('itinerary')->nullable()->after('description');
            $table->text('included')->nullable()->after('inclusions');
            $table->text('excluded')->nullable()->after('included');
        });

        Schema::table('tourism_experiences', function (Blueprint $table) {
            $table->dropColumn(['capacity', 'inclusions']);
        });
    }

    public function down(): void
    {
        Schema::table('tourism_experiences', function (Blueprint $table) {
            $table->unsignedInteger('capacity')->default(1);
            $table->json('inclusions')->nullable();
        });

        Schema::table('tourism_experiences', function (Blueprint $table) {
            $table->dropColumn(['itinerary', 'included', 'excluded']);
        });
    }
};
