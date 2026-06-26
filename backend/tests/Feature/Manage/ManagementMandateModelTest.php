<?php

namespace Tests\Feature\Manage;

use App\Modules\Manage\Enums\MandateStatus;
use App\Modules\Manage\Models\ManagementMandate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests du socle de données des mandats de gestion (phase B4.1).
 */
class ManagementMandateModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_mandat_est_lie_a_un_bien_et_a_son_proprietaire(): void
    {
        $mandate = ManagementMandate::factory()->create();

        $this->assertNotNull($mandate->property);
        $this->assertNotNull($mandate->owner);
        // Cohérence : le propriétaire du mandat est bien celui du bien.
        $this->assertSame($mandate->owner_id, $mandate->property->owner_id);
    }

    public function test_le_statut_et_la_commission_sont_castes(): void
    {
        $mandate = ManagementMandate::factory()->create([
            'status' => MandateStatus::ACTIF->value,
            'commission_rate' => 10,
        ]);

        $this->assertSame(MandateStatus::ACTIF, $mandate->status);
        $this->assertSame('10.00', (string) $mandate->commission_rate);
    }
}
