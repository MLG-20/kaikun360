<?php

namespace Tests\Feature\Diaspora;

use App\Models\Report;
use App\Models\User;
use App\Modules\Diaspora\Enums\DiasporaPriority;
use App\Modules\Diaspora\Enums\DiasporaProjectStatus;
use App\Modules\Diaspora\Enums\DiasporaProjectType;
use App\Modules\Diaspora\Models\DiasporaProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests de la couche de données des projets diaspora (phase B8.1) : casts,
 * relations client/agent et rapports polymorphes partagés avec Build.
 */
class DiasporaProjectModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_projet_se_cree_avec_ses_casts(): void
    {
        $project = DiasporaProject::factory()->create([
            'project_type' => DiasporaProjectType::CONSTRUCTION->value,
            'priority' => DiasporaPriority::HAUTE->value,
        ]);
        $project->refresh();

        $this->assertSame(DiasporaProjectType::CONSTRUCTION, $project->project_type);
        $this->assertSame(DiasporaPriority::HAUTE, $project->priority);
        $this->assertSame(DiasporaProjectStatus::NOUVEAU, $project->status);
    }

    public function test_un_projet_appartient_a_un_client_et_a_un_agent_optionnel(): void
    {
        $client = User::factory()->create();
        $agent = User::factory()->create();
        $project = DiasporaProject::factory()->assigned($agent)->create(['client_id' => $client->id]);

        $this->assertTrue($project->client->is($client));
        $this->assertTrue($project->agent->is($agent));
        $this->assertSame(DiasporaProjectStatus::EN_COURS, $project->status);
    }

    public function test_un_projet_a_des_rapports_polymorphes(): void
    {
        $project = DiasporaProject::factory()->create();
        Report::factory()->count(2)->create([
            'reportable_type' => DiasporaProject::class,
            'reportable_id' => $project->id,
        ]);
        Report::factory()->create(); // rattaché à autre chose (Construction)

        $this->assertCount(2, $project->reports);
        $this->assertInstanceOf(DiasporaProject::class, $project->reports->first()->reportable);
    }

    public function test_la_priorite_porte_un_poids_de_tri(): void
    {
        $this->assertGreaterThan(
            DiasporaPriority::NORMALE->weight(),
            DiasporaPriority::STRATEGIQUE->weight()
        );
    }
}
