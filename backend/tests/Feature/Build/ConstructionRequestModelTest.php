<?php

namespace Tests\Feature\Build;

use App\Models\User;
use App\Modules\Build\Enums\ConstructionObjective;
use App\Modules\Build\Enums\ConstructionRequestStatus;
use App\Modules\Build\Enums\FinishLevel;
use App\Modules\Build\Models\ConstructionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests de la couche de données des demandes de construction (phase B5.1) :
 * persistance, casts (enums/entiers) et relation au client.
 */
class ConstructionRequestModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_demande_se_cree_avec_ses_casts(): void
    {
        $request = ConstructionRequest::factory()->create([
            'objective' => ConstructionObjective::RENOVATION->value,
            'finish_level' => FinishLevel::PREMIUM->value,
            'surface_m2' => 120,
        ]);

        $request->refresh();

        $this->assertInstanceOf(ConstructionObjective::class, $request->objective);
        $this->assertInstanceOf(FinishLevel::class, $request->finish_level);
        $this->assertSame(ConstructionRequestStatus::SOUMISE, $request->status);
        $this->assertSame(120, $request->surface_m2);
    }

    public function test_une_demande_appartient_a_un_client(): void
    {
        $client = User::factory()->create();
        $request = ConstructionRequest::factory()->create(['client_id' => $client->id]);

        $this->assertTrue($request->client->is($client));
    }

    public function test_le_statut_par_defaut_est_soumise(): void
    {
        $request = ConstructionRequest::factory()->create();

        $this->assertSame(ConstructionRequestStatus::SOUMISE, $request->fresh()->status);
    }
}
