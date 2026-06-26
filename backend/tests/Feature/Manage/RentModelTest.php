<?php

namespace Tests\Feature\Manage;

use App\Modules\Manage\Enums\RentStatus;
use App\Modules\Manage\Models\ManagementMandate;
use App\Modules\Manage\Models\Rent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests du socle de données des loyers (phase B4.2).
 */
class RentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_loyer_appartient_a_un_mandat(): void
    {
        $mandate = ManagementMandate::factory()->create();
        $rent = Rent::factory()->create(['mandate_id' => $mandate->id]);

        $this->assertTrue($rent->mandate->is($mandate));
    }

    public function test_un_mandat_possede_plusieurs_loyers(): void
    {
        $mandate = ManagementMandate::factory()->create();
        Rent::factory()->count(3)->create(['mandate_id' => $mandate->id]);

        $this->assertCount(3, $mandate->rents);
    }

    public function test_le_statut_est_caste_et_l_etat_paye_fonctionne(): void
    {
        $rent = Rent::factory()->paid()->create();

        $this->assertSame(RentStatus::PAYE, $rent->status);
        $this->assertNotNull($rent->paid_at);
    }
}
