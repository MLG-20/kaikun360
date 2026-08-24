<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Table `provider_categories` — nomenclature des catégories de service
 * prestataire, désormais EXTENSIBLE (au lieu de l'enum PHP fermé `ProviderCategory`).
 *
 * Un prestataire qui ne se reconnaît dans aucune catégorie peut en proposer une
 * (`POST /providers/categories`) : elle entre `en_attente`, utilisable
 * immédiatement par son auteur, mais invisible pour les autres prestataires tant
 * qu'un admin ne l'a pas validée depuis la file de modération générique
 * (`ProviderCategoryValidator`).
 *
 * Les 7 valeurs historiques de l'enum sont insérées ci-dessous en `valide` : la
 * donnée de référence existe dès le déploiement (`php artisan migrate`), sans
 * dépendre d'un seeder à lancer manuellement en prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            // en_attente / valide / refuse — cf. ProviderCategoryStatus.
            $table->string('status')->default('en_attente')->index();
            $table->foreignId('created_by_provider_id')->nullable()
                ->constrained('providers')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('provider_categories')->insert(array_map(
            fn (array $c) => [...$c, 'status' => 'valide', 'created_at' => now(), 'updated_at' => now()],
            [
                ['key' => 'restauration', 'label' => 'Restauration'],
                ['key' => 'animation', 'label' => 'Animation'],
                ['key' => 'guide', 'label' => 'Guide touristique'],
                ['key' => 'transport', 'label' => 'Transport'],
                ['key' => 'evenementiel', 'label' => 'Événementiel'],
                ['key' => 'artisanat', 'label' => 'Artisanat'],
                // Conservée en base (les profils existants la portent encore) mais
                // retirée du sélecteur — remplacée par « Proposer une catégorie ».
                ['key' => 'autre', 'label' => 'Autre'],
            ],
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_categories');
    }
};
