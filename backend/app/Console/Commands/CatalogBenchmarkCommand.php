<?php

namespace App\Console\Commands;

use App\Modules\Immo\Models\Property;
use App\Support\Cache\CatalogCache;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * B17.4 — Test de charge local des endpoints de catalogue/recherche.
 *
 * Benchmark pragmatique, sans infrastructure externe : on amorce un volume de
 * biens publiés puis on rejoue N requêtes réelles à travers le kernel HTTP
 * complet (middlewares, sérialisation Resource, cache) sur l'endpoint de
 * catalogue. On compare deux régimes :
 *   - « à froid » : cache invalidé avant chaque requête → mesure le coût réel
 *     de la requête SQL + sérialisation (valide les index B17.1) ;
 *   - « à chaud » : cache actif → mesure le service depuis Redis (B17.2).
 *
 * On reporte latence moyenne / p95 et nombre de requêtes SQL par appel.
 *
 * Le jeu de données est amorcé dans une transaction annulée en fin de course :
 * la base n'est pas polluée. À lancer de préférence sur une base de dev.
 *
 * Exemple : php artisan catalog:benchmark --rows=500 --requests=100
 */
class CatalogBenchmarkCommand extends Command
{
    protected $signature = 'catalog:benchmark
        {--rows=200 : Nombre de biens publiés à amorcer}
        {--requests=50 : Nombre de requêtes par régime}
        {--endpoint=/api/v1/properties : Endpoint de catalogue à mesurer}';

    protected $description = 'Test de charge local des endpoints de catalogue (latence + requêtes SQL, à froid vs à chaud).';

    public function handle(Kernel $kernel): int
    {
        $rows = max(1, (int) $this->option('rows'));
        $requests = max(1, (int) $this->option('requests'));
        $endpoint = (string) $this->option('endpoint');

        // Le throttle:api (60/min) fausserait la mesure : on le neutralise pour
        // la durée de ce process uniquement.
        RateLimiter::for('api', fn () => \Illuminate\Cache\RateLimiting\Limit::none());

        $this->info("Amorçage de {$rows} biens publiés (transaction annulée en fin de test)…");
        DB::beginTransaction();

        try {
            Property::factory()->count($rows)->published()->create();

            $this->line("Endpoint mesuré : <comment>{$endpoint}</comment>");
            $this->newLine();

            $cold = $this->measure($kernel, $endpoint, $requests, flushEachTime: true);
            $warm = $this->measure($kernel, $endpoint, $requests, flushEachTime: false);

            $this->table(
                ['Régime', 'Requêtes', 'Latence moy.', 'Latence p95', 'Requêtes SQL / appel'],
                [
                    ['À froid (cache vidé)', $requests, $this->ms($cold['avg']), $this->ms($cold['p95']), $cold['queries']],
                    ['À chaud (cache actif)', $requests, $this->ms($warm['avg']), $this->ms($warm['p95']), $warm['queries']],
                ]
            );

            $this->newLine();
            $this->info(sprintf(
                'Gain du cache : %.1f× plus rapide, %d requête(s) SQL au lieu de %d.',
                $cold['avg'] > 0 ? $cold['avg'] / max($warm['avg'], 0.0001) : 0,
                $warm['queries'],
                $cold['queries'],
            ));
        } finally {
            DB::rollBack();
            $this->line('Jeu de données de test annulé (rollback).');
        }

        return self::SUCCESS;
    }

    /**
     * Rejoue $requests appels et agrège latence + requêtes SQL.
     *
     * @return array{avg: float, p95: float, queries: int}
     */
    private function measure(Kernel $kernel, string $endpoint, int $requests, bool $flushEachTime): array
    {
        $times = [];
        $queries = 0;

        // Premier appel « à chaud » : on peuple le cache une fois hors mesure.
        if (! $flushEachTime) {
            $kernel->handle(Request::create($endpoint, 'GET'));
        }

        for ($i = 0; $i < $requests; $i++) {
            if ($flushEachTime) {
                CatalogCache::flush('properties');
            }

            DB::flushQueryLog();
            DB::enableQueryLog();

            $start = microtime(true);
            $response = $kernel->handle(Request::create($endpoint, 'GET'));
            $times[] = microtime(true) - $start;

            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            if ($response->getStatusCode() !== 200) {
                $this->warn("Réponse inattendue ({$response->getStatusCode()}) sur {$endpoint}.");
            }
        }

        sort($times);
        $avg = array_sum($times) / count($times);
        $p95 = $times[(int) ceil(0.95 * count($times)) - 1];

        return ['avg' => $avg, 'p95' => $p95, 'queries' => $queries];
    }

    private function ms(float $seconds): string
    {
        return number_format($seconds * 1000, 2).' ms';
    }
}
