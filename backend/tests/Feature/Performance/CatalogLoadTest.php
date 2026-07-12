<?php

namespace Tests\Feature\Performance;

use App\Modules\Immo\Models\Property;
use App\Support\Cache\CatalogCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * B17.4 — Caractéristique de charge du catalogue (garde-fou CI).
 *
 * Complète le benchmark manuel `php artisan catalog:benchmark`. On prouve deux
 * propriétés qui garantissent que le catalogue tient la charge :
 *   1. à froid, le nombre de requêtes SQL est CONSTANT quel que soit le volume
 *      (aucun coût par ligne : les index B17.1 et l'eager loading font le travail) ;
 *   2. à chaud, la requête est servie depuis le cache sans toucher la base
 *      (0 requête SQL — B17.2).
 */
class CatalogLoadTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_nombre_de_requetes_sql_du_catalogue_est_independant_du_volume(): void
    {
        // Petit volume.
        Property::factory()->count(3)->published()->create();
        $petit = $this->coldQueryCount('/api/v1/properties');

        // Volume dix fois supérieur : le coût SQL ne doit pas augmenter.
        Property::factory()->count(30)->published()->create();
        $gros = $this->coldQueryCount('/api/v1/properties');

        $this->assertSame(
            $petit,
            $gros,
            "Le nombre de requêtes SQL du catalogue doit être constant (petit={$petit}, gros={$gros}) : un coût par ligne s'est introduit."
        );

        // Filet supplémentaire : ce coût fixe reste faible.
        $this->assertLessThanOrEqual(8, $gros, "Coût SQL fixe anormalement élevé ({$gros} requêtes).");
    }

    public function test_le_catalogue_est_servi_du_cache_sans_requete_sql(): void
    {
        Property::factory()->count(10)->published()->create();

        // Premier appel : peuple le cache (peut toucher la base).
        $this->getJson('/api/v1/properties')->assertOk();

        // Deuxième appel identique : servi entièrement depuis le cache.
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/properties')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(0, $queries, "Le catalogue à chaud ne doit émettre aucune requête SQL (émises : {$queries}).");
    }

    private function coldQueryCount(string $uri): int
    {
        CatalogCache::flush('properties'); // force le régime « à froid »

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson($uri)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
