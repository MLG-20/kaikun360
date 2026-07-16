<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute au compte utilisateur une localisation structurée et une adresse
 * (F3.2b — édition des coordonnées depuis l'espace client).
 *
 * On mire exactement la localisation des biens (`properties`) : trois clés
 * étrangères optionnelles Région → Département → Commune (référentiel géo
 * existant), pour alimenter des menus déroulants en cascade, plus une adresse
 * libre (rue/quartier). La colonne `city` (texte libre historique) est
 * conservée pour compatibilité : elle est désormais **dérivée** de la commune /
 * du département choisi (mise à jour à l'enregistrement du profil).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Adresse libre (rue, quartier…) — même type que `properties.address`.
            $table->text('address')->nullable()->after('city');

            // Localisation structurée (cascade), toutes optionnelles.
            $table->foreignId('region_id')->nullable()->after('address')->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('region_id')->constrained()->nullOnDelete();
            $table->foreignId('commune_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commune_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('region_id');
            $table->dropColumn('address');
        });
    }
};
