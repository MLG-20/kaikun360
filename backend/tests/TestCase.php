<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    /**
     * Les 7 catégories de référence des prestataires (F5.6). En prod, cette
     * donnée existe dès le déploiement : la migration
     * `create_provider_categories_table` l'insère elle-même dans son `up()`.
     * Mais `php artisan schema:dump` ne capture que la structure et le
     * journal des migrations — pas les données qu'une migration insère — donc
     * toute base rechargée depuis le dump squashé (la suite de tests) en est
     * privée. `ProviderFactory` pioche pourtant une catégorie existante à
     * chaque `Provider::factory()`, et `RefreshDatabase` fait tourner chaque
     * test dans une transaction annulée à la fin : un seed en setUp()
     * (plutôt qu'une seule fois pour tout le run) est donc nécessaire à
     * CHAQUE test. Un upsert en une requête reste négligeable même répété
     * ~1200 fois.
     *
     * @var list<array{key: string, label: string}>
     */
    private const PROVIDER_CATEGORIES = [
        ['key' => 'restauration', 'label' => 'Restauration'],
        ['key' => 'animation', 'label' => 'Animation'],
        ['key' => 'guide', 'label' => 'Guide touristique'],
        ['key' => 'transport', 'label' => 'Transport'],
        ['key' => 'evenementiel', 'label' => 'Événementiel'],
        ['key' => 'artisanat', 'label' => 'Artisanat'],
        ['key' => 'autre', 'label' => 'Autre'],
    ];

    /**
     * Le cache de test tourne en driver `array`, qui vit pour tout le
     * process PHPUnit — pas par test. `RefreshDatabase` réinitialise la
     * BDD entre les tests mais pas ce cache, donc une valeur mise en cache
     * (ex: SettingsRepository::CACHE_KEY) peut fuiter d'un test à l'autre
     * selon l'ordre d'exécution. On repart d'un cache vide avant chaque test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        if (Schema::hasTable('provider_categories')) {
            DB::table('provider_categories')->upsert(
                array_map(fn (array $c) => [...$c, 'status' => 'valide', 'created_at' => now(), 'updated_at' => now()], self::PROVIDER_CATEGORIES),
                ['key'],
                ['label', 'status', 'updated_at'],
            );
        }
    }
}
