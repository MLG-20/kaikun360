<?php

namespace Tests\Feature\Manage;

use App\Modules\Manage\Enums\OwnerPayoutStatus;
use App\Modules\Manage\Models\ManagementMandate;
use App\Modules\Manage\Models\OwnerPayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests du socle de données des reversements au propriétaire (phase B4.4).
 */
class OwnerPayoutModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_reversement_est_lie_a_un_mandat_et_au_proprietaire(): void
    {
        $payout = OwnerPayout::factory()->create();

        $this->assertNotNull($payout->mandate);
        // Le bénéficiaire est bien le propriétaire du mandat.
        $this->assertSame($payout->mandate->owner_id, $payout->owner_id);
    }

    public function test_un_mandat_possede_plusieurs_reversements(): void
    {
        $mandate = ManagementMandate::factory()->create();
        OwnerPayout::factory()->count(2)->create([
            'mandate_id' => $mandate->id,
            'owner_id' => $mandate->owner_id,
        ]);

        $this->assertCount(2, $mandate->payouts);
    }

    public function test_l_etat_effectue_est_horodate(): void
    {
        $payout = OwnerPayout::factory()->done()->create();

        $this->assertSame(OwnerPayoutStatus::EFFECTUE, $payout->status);
        $this->assertNotNull($payout->paid_at);
    }
}
