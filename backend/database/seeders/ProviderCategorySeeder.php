<?php

namespace Database\Seeders;

use App\Modules\Pro\Models\ProviderCategory;
use Illuminate\Database\Seeder;

/**
 * Catégories de référence des prestataires (F5.6).
 *
 * Doublon volontaire des lignes insérées par la migration
 * `2026_08_23_090000_create_provider_categories_table` : cette donnée fait
 * partie de la migration elle-même (pas de seeder à lancer manuellement en
 * prod), mais `php artisan schema:dump` ne capture QUE la structure et le
 * journal des migrations — pas les données qu'une migration insère dans son
 * `up()`. Toute base reconstruite depuis le dump squashé (la suite de tests)
 * perd donc ces lignes. `updateOrCreate` la rend sûre à rejouer dans les deux
 * cas (migration normale déjà passée, ou dump squashé).
 */
class ProviderCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['key' => 'restauration', 'label' => 'Restauration'],
            ['key' => 'animation', 'label' => 'Animation'],
            ['key' => 'guide', 'label' => 'Guide touristique'],
            ['key' => 'transport', 'label' => 'Transport'],
            ['key' => 'evenementiel', 'label' => 'Événementiel'],
            ['key' => 'artisanat', 'label' => 'Artisanat'],
            ['key' => 'autre', 'label' => 'Autre'],
        ] as $category) {
            ProviderCategory::query()->updateOrCreate(
                ['key' => $category['key']],
                [...$category, 'status' => 'valide'],
            );
        }
    }
}
