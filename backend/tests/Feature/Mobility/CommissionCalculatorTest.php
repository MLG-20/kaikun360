<?php

namespace Tests\Feature\Mobility;

use App\Modules\Mobility\Services\CommissionCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du calcul de commission (phase B7.4).
 */
class CommissionCalculatorTest extends TestCase
{
    public function test_commission_au_taux_par_defaut(): void
    {
        $calc = new CommissionCalculator();
        // 12 % de 100 000 = 12 000.
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
