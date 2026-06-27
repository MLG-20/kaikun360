<?php

namespace Tests\Feature\Build;

use App\Modules\Build\Enums\ConstructionObjective;
use App\Modules\Build\Enums\FinishLevel;
use App\Modules\Build\Services\ConstructionEstimator;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du simulateur de budget (phase B5.4). Pas d'accès base de
 * données : la logique est purement arithmétique.
 */
class ConstructionEstimatorTest extends TestCase
{
    private ConstructionEstimator $estimator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->estimator = new ConstructionEstimator();
    }

    public function test_estimation_neuf_standard(): void
    {
        // 250 000 × 100 × 1.0 = 25 000 000.
        $this->assertSame(25_000_000, $this->estimator->estimate(
            ConstructionObjective::CONSTRUCTION_NEUVE, 100, FinishLevel::STANDARD
        ));
    }

    public function test_le_premium_coute_plus_cher_que_l_economique(): void
    {
        $eco = $this->estimator->estimate(ConstructionObjective::CONSTRUCTION_NEUVE, 120, FinishLevel::ECONOMIQUE);
        $std = $this->estimator->estimate(ConstructionObjective::CONSTRUCTION_NEUVE, 120, FinishLevel::STANDARD);
        $premium = $this->estimator->estimate(ConstructionObjective::CONSTRUCTION_NEUVE, 120, FinishLevel::PREMIUM);

        $this->assertGreaterThan($eco, $std);
        $this->assertGreaterThan($std, $premium);
    }

    public function test_la_renovation_coute_moins_cher_que_le_neuf(): void
    {
        $neuf = $this->estimator->estimate(ConstructionObjective::CONSTRUCTION_NEUVE, 150, FinishLevel::STANDARD);
        $reno = $this->estimator->estimate(ConstructionObjective::RENOVATION, 150, FinishLevel::STANDARD);

        $this->assertLessThan($neuf, $reno);
    }

    public function test_l_estimation_est_arrondie_au_pas(): void
    {
        // 250 000 × 100 × 1.35 = 33 750 000 → arrondi à 33 800 000.
        $this->assertSame(33_800_000, $this->estimator->estimate(
            ConstructionObjective::CONSTRUCTION_NEUVE, 100, FinishLevel::PREMIUM
        ));
    }

    public function test_surface_nulle_donne_zero(): void
    {
        $this->assertSame(0, $this->estimator->estimate(
            ConstructionObjective::CONSTRUCTION_NEUVE, 0, FinishLevel::STANDARD
        ));
    }

    public function test_le_detail_expose_les_parametres(): void
    {
        $breakdown = $this->estimator->breakdown(
            ConstructionObjective::EXTENSION, 90, FinishLevel::STANDARD
        );

        $this->assertSame('extension', $breakdown['objective']);
        $this->assertSame(220_000, $breakdown['price_per_m2_xof']);
        $this->assertSame(1.0, $breakdown['finish_coefficient']);
        $this->assertSame(19_800_000, $breakdown['estimated_cost_xof']);
    }
}
