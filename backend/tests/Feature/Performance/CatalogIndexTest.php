<?php

namespace Tests\Feature\Performance;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * B17.1 — Durcissement & performance.
 *
 * Vérifie que les index de recherche des catalogues sont bien présents dans le
 * schéma chargé par les tests (dump `database/schema/mysql-schema.sql`). Ce
 * garde-fou empêche qu'une régénération de dump ou une migration ultérieure ne
 * fasse silencieusement disparaître un index critique pour la performance des
 * endpoints de catalogue/recherche.
 */
class CatalogIndexTest extends TestCase
{
    /**
     * Table => index attendus (nom conventionnel Laravel : <table>_<colonne>_index).
     *
     * @var array<string, list<string>>
     */
    private const EXPECTED_INDEXES = [
        'stays' => ['stays_price_per_night_xof_index'],
        'vehicles' => ['vehicles_price_per_day_xof_index'],
        'mobility_services' => [
            'mobility_services_departure_index',
            'mobility_services_destination_index',
        ],
        'tourism_experiences' => [
            'tourism_experiences_destination_index',
            'tourism_experiences_price_xof_index',
        ],
        'payments' => ['payments_status_index'],
    ];

    public function test_les_index_de_recherche_des_catalogues_existent(): void
    {
        foreach (self::EXPECTED_INDEXES as $table => $indexes) {
            $existing = collect(DB::select("SHOW INDEX FROM `{$table}`"))
                ->pluck('Key_name')
                ->unique()
                ->all();

            foreach ($indexes as $index) {
                $this->assertContains(
                    $index,
                    $existing,
                    "L'index `{$index}` est manquant sur la table `{$table}` "
                    .'(régression de performance B17.1).'
                );
            }
        }
    }
}
