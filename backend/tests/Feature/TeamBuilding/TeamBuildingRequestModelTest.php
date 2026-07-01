<?php

namespace Tests\Feature\TeamBuilding;

use App\Models\User;
use App\Modules\TeamBuilding\Enums\TeamBuildingRequestStatus;
use App\Modules\TeamBuilding\Models\TeamBuildingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests de la couche de données des demandes de team building (phase B9.1).
 */
class TeamBuildingRequestModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_demande_se_cree_avec_ses_casts(): void
    {
        $request = TeamBuildingRequest::factory()->create([
            'participants' => 25,
            'needs' => ['hebergement' => true, 'animation' => false],
        ]);
        $request->refresh();

        $this->assertSame(TeamBuildingRequestStatus::NOUVEAU, $request->status);
        $this->assertSame(25, $request->participants);
        $this->assertIsArray($request->needs);
        $this->assertTrue($request->needs['hebergement']);
    }

    public function test_une_demande_appartient_a_une_entreprise(): void
    {
        $company = User::factory()->create();
        $request = TeamBuildingRequest::factory()->create(['company_id' => $company->id]);

        $this->assertTrue($request->company->is($company));
    }
}
