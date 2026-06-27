<?php

namespace Tests\Feature\Build;

use App\Modules\Build\Enums\ConstructionObjective;
use App\Modules\Build\Enums\MilestoneStatus;
use App\Modules\Build\Models\ConstructionMilestone;
use App\Modules\Build\Models\ConstructionRequest;
use App\Modules\Build\Services\ConstructionMilestoneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests des jalons de chantier (phase B5.3) : couche de données et logique de
 * planification par défaut (étapes selon l'objectif).
 */
class ConstructionMilestoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_jalon_appartient_a_une_demande_avec_ses_casts(): void
    {
        $request = ConstructionRequest::factory()->create();
        $milestone = ConstructionMilestone::factory()->done()->create([
            'construction_request_id' => $request->id,
        ]);

        $this->assertTrue($milestone->constructionRequest->is($request));
        $this->assertSame(MilestoneStatus::TERMINE, $milestone->fresh()->status);
        $this->assertNotNull($milestone->fresh()->actual_date);
    }

    public function test_les_jalons_sont_ordonnes_par_position(): void
    {
        $request = ConstructionRequest::factory()->create();
        ConstructionMilestone::factory()->create(['construction_request_id' => $request->id, 'position' => 3]);
        ConstructionMilestone::factory()->create(['construction_request_id' => $request->id, 'position' => 1]);
        ConstructionMilestone::factory()->create(['construction_request_id' => $request->id, 'position' => 2]);

        $this->assertSame([1, 2, 3], $request->milestones->pluck('position')->all());
    }

    public function test_le_service_seme_les_jalons_par_defaut_neuf(): void
    {
        $request = ConstructionRequest::factory()->create([
            'objective' => ConstructionObjective::CONSTRUCTION_NEUVE->value,
        ]);

        $milestones = app(ConstructionMilestoneService::class)->seedDefault($request);

        $this->assertCount(7, $milestones);
        $this->assertSame('Études & permis', $request->milestones->first()->name);
        $this->assertSame('Livraison', $request->milestones->last()->name);
        $this->assertSame(MilestoneStatus::A_VENIR, $request->milestones->first()->status);
    }

    public function test_le_parcours_renovation_ne_contient_pas_de_fondations(): void
    {
        $request = ConstructionRequest::factory()->create([
            'objective' => ConstructionObjective::RENOVATION->value,
        ]);

        $service = app(ConstructionMilestoneService::class);
        $service->seedDefault($request);

        $names = $request->milestones->pluck('name')->all();
        $this->assertContains('Diagnostic', $names);
        $this->assertNotContains('Fondations', $names);
    }

    public function test_le_service_est_idempotent(): void
    {
        $request = ConstructionRequest::factory()->create();
        $service = app(ConstructionMilestoneService::class);

        $service->seedDefault($request);
        $service->seedDefault($request); // second appel : ne duplique pas

        $this->assertSame(
            $request->fresh()->milestones->count(),
            $request->milestones()->count()
        );
        $this->assertGreaterThan(0, $request->milestones()->count());
    }
}
