<?php

namespace Tests\Feature\Mobility;

use App\Modules\Mobility\Services\CommissionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests du calcul de commission (phase B7.4).
 *
 * Depuis B13.4, le taux par défaut est lu via `Settings` (paramétrable au
 * back-office) : ce test s'appuie donc sur l'application (cache + table
 * `settings`) et vérifie le comportement en l'absence de surcharge.
 */
class CommissionCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_commission_au_taux_par_defaut(): void
    {
        $calc = new CommissionCalculator();
        // Aucun réglage saisi → taux de repli 12 % ; 12 % de 100 000 = 12 000.
        $this->assertSame(12_000, $calc->commissionFor(100_000));
    }

    public function test_commission_avec_taux_specifique(): void
    {
        $calc = new CommissionCalculator();
        $this->assertSame(15_000, $calc->commissionFor(100_000, 15.0));
    }

    public function test_montant_negatif_donne_zero(): void
    {
        $calc = new CommissionCalculator();
        $this->assertSame(0, $calc->commissionFor(-5_000));
    }
}
