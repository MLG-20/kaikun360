<?php

namespace Tests\Feature\Build;

use App\Modules\Build\Enums\ConstructionObjective;
use App\Modules\Build\Enums\ConstructionZone;
use App\Modules\Build\Enums\FinishLevel;
use App\Modules\Build\Services\ConstructionEstimator;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests du simulateur de budget (B5.4, enrichi). Le calcul est arithmétique
 * mais lit désormais son barème depuis les réglages (`build.pricing`), gérables
 * au back-office — d'où le besoin du conteneur/base (RefreshDatabase).
 */
class ConstructionEstimatorTest extends TestCase
{
    use RefreshDatabase;

    private ConstructionEstimator $estimator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->estimator = new ConstructionEstimator();
    }

    // --- Coût des travaux (ancre historique) ---------------------------------

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

    // --- Niveaux, zone, foncier (enrichissement) -----------------------------

    public function test_les_niveaux_multiplient_la_surface(): void
    {
        // R+1 (2 niveaux) = deux fois la surface au sol.
        $rdc = $this->estimator->breakdown(ConstructionObjective::CONSTRUCTION_NEUVE, 100, FinishLevel::STANDARD, 1);
        $r1 = $this->estimator->breakdown(ConstructionObjective::CONSTRUCTION_NEUVE, 100, FinishLevel::STANDARD, 2);

        $this->assertSame(200, $r1['inputs']['total_surface_m2']);
        $this->assertSame($rdc['works']['cost_xof'] * 2, $r1['works']['cost_xof']);
    }

    public function test_les_zones_eloignees_surencherissent_les_travaux(): void
    {
        $dakar = $this->estimator->breakdown(
            ConstructionObjective::CONSTRUCTION_NEUVE, 120, FinishLevel::STANDARD, 1, ConstructionZone::DAKAR
        );
        $loin = $this->estimator->breakdown(
            ConstructionObjective::CONSTRUCTION_NEUVE, 120, FinishLevel::STANDARD, 1, ConstructionZone::ZONES_ELOIGNEES
        );

        $this->assertGreaterThan($dakar['works']['cost_xof'], $loin['works']['cost_xof']);
    }

    public function test_le_terrain_ajoute_son_cout_et_ses_frais_d_acquisition(): void
    {
        $sansTerrain = $this->estimator->breakdown(
            ConstructionObjective::CONSTRUCTION_NEUVE, 100, FinishLevel::STANDARD, 1, ConstructionZone::DAKAR, 0
        );
        $avecTerrain = $this->estimator->breakdown(
            ConstructionObjective::CONSTRUCTION_NEUVE, 100, FinishLevel::STANDARD, 1, ConstructionZone::DAKAR, 10_000_000
        );

        $this->assertTrue($sansTerrain['land']['included']);
        $this->assertSame(0, $sansTerrain['land']['total_xof']);

        $this->assertFalse($avecTerrain['land']['included']);
        // Frais d'acquisition = 10 % de 10 000 000 = 1 000 000.
        $this->assertSame(1_000_000, $avecTerrain['land']['acquisition_fees_xof']);
        $this->assertSame(11_000_000, $avecTerrain['land']['total_xof']);
    }

    public function test_le_total_general_somme_travaux_frais_et_foncier(): void
    {
        $b = $this->estimator->breakdown(
            ConstructionObjective::CONSTRUCTION_NEUVE, 100, FinishLevel::STANDARD, 1, ConstructionZone::DAKAR, 5_000_000
        );

        $expected = $b['works']['cost_xof'] + $b['fees']['total_xof'] + $b['land']['total_xof'];
        $this->assertSame($expected, $b['grand_total_xof']);
    }

    public function test_la_repartition_des_travaux_somme_au_cout_des_travaux(): void
    {
        $b = $this->estimator->breakdown(ConstructionObjective::CONSTRUCTION_NEUVE, 137, FinishLevel::PREMIUM, 2);

        $sum = array_sum(array_column($b['works']['breakdown'], 'amount_xof'));
        $this->assertSame($b['works']['cost_xof'], $sum);

        $milestones = array_sum(array_column($b['works']['milestones'], 'amount_xof'));
        $this->assertSame($b['works']['cost_xof'], $milestones);
    }

    public function test_la_projection_locative_expose_les_deux_modes(): void
    {
        $b = $this->estimator->breakdown(ConstructionObjective::CONSTRUCTION_NEUVE, 100, FinishLevel::STANDARD);

        $this->assertArrayHasKey('longue_duree', $b['rental']);
        $this->assertArrayHasKey('nuitee', $b['rental']);
        // La nuitée rapporte davantage que la location longue durée.
        $this->assertGreaterThan(
            $b['rental']['longue_duree']['monthly_income_xof'],
            $b['rental']['nuitee']['monthly_income_xof'],
        );
    }

    // --- Pilotage par les réglages (back-office) ------------------------------

    public function test_le_bareme_est_pilote_par_les_reglages(): void
    {
        $avant = $this->estimator->estimate(ConstructionObjective::CONSTRUCTION_NEUVE, 100, FinishLevel::STANDARD);
        $this->assertSame(25_000_000, $avant);

        // L'équipe admin double le prix au m² du neuf via les réglages.
        Settings::set('build.pricing', ['price_m2' => ['construction_neuve' => 500_000]]);

        $apres = $this->estimator->estimate(ConstructionObjective::CONSTRUCTION_NEUVE, 100, FinishLevel::STANDARD);
        $this->assertSame(50_000_000, $apres);

        // La surcharge est PARTIELLE : les autres coefficients restent par défaut.
        $reno = $this->estimator->estimate(ConstructionObjective::RENOVATION, 100, FinishLevel::STANDARD);
        $this->assertSame(15_000_000, $reno);
    }
}
