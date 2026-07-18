<?php

use App\Modules\Immo\Models\Property;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rend la table `favorites` POLYMORPHE (tous univers).
 *
 * Historique : les favoris ne portaient que sur des biens immobiliers
 * (`property_id`). On les généralise à tous les univers favorisables (bien,
 * nuitée, véhicule, expérience, service de mobilité) en remplaçant `property_id`
 * par un couple polymorphe `favoritable_type` / `favoritable_id` — même
 * convention que `bookings.bookable_*` (nom de classe complet stocké).
 *
 * Reprise des données : chaque favori existant est converti en favori de type
 * Property (aucune perte). L'unicité passe à (user, type, id) : impossible de
 * favoriser deux fois le même élément.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Colonnes polymorphes (nullable le temps de la reprise de données).
        Schema::table('favorites', function (Blueprint $table) {
            $table->string('favoritable_type')->nullable()->after('user_id');
            $table->unsignedBigInteger('favoritable_id')->nullable()->after('favoritable_type');
        });

        // 2) Reprise : les favoris existants deviennent des favoris de bien.
        DB::table('favorites')->update([
            'favoritable_type' => Property::class,
            'favoritable_id' => DB::raw('property_id'),
        ]);

        // 3) Nouvelle unicité polymorphe (user_id EN TÊTE) + index de recherche.
        // On l'ajoute AVANT de retirer l'ancienne unicité : sous MySQL, l'index
        // (user_id, property_id) sert aussi la clé étrangère sur `user_id` — le
        // nouvel index (user_id, …) prend le relais, sinon le drop échoue (1553).
        Schema::table('favorites', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'favoritable_type', 'favoritable_id'],
                'favorites_user_favoritable_unique',
            );
            $table->index(['favoritable_type', 'favoritable_id']);
        });

        // 4) Retrait de l'ancienne clé étrangère, de l'ancienne unicité et de la
        // colonne property_id (la FK d'abord, elle s'appuie sur un index).
        Schema::table('favorites', function (Blueprint $table) {
            $table->dropForeign(['property_id']);
        });
        Schema::table('favorites', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'property_id']);
        });
        Schema::table('favorites', function (Blueprint $table) {
            $table->dropColumn('property_id');
        });
    }

    public function down(): void
    {
        // Retour arrière : on ne conserve que les favoris de biens immobiliers.
        Schema::table('favorites', function (Blueprint $table) {
            $table->dropUnique('favorites_user_favoritable_unique');
            $table->dropIndex(['favoritable_type', 'favoritable_id']);
            $table->foreignId('property_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });

        DB::table('favorites')
            ->where('favoritable_type', Property::class)
            ->update(['property_id' => DB::raw('favoritable_id')]);

        // Les favoris d'autres univers n'ont pas d'équivalent : on les supprime.
        DB::table('favorites')->whereNull('property_id')->delete();

        Schema::table('favorites', function (Blueprint $table) {
            $table->dropColumn(['favoritable_type', 'favoritable_id']);
            $table->unique(['user_id', 'property_id']);
        });
    }
};
