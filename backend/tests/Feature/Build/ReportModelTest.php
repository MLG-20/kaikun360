<?php

namespace Tests\Feature\Build;

use App\Models\Report;
use App\Modules\Build\Enums\ReportType;
use App\Modules\Build\Models\ConstructionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests de la couche de données des rapports de suivi (phase B5.2) :
 * relation polymorphe, casts (photos en tableau, type enum) et morphMany.
 */
class ReportModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_rapport_est_rattache_polymorphiquement_a_une_demande(): void
    {
        $request = ConstructionRequest::factory()->create();
        $report = Report::factory()->create([
            'reportable_type' => ConstructionRequest::class,
            'reportable_id' => $request->id,
        ]);

        $this->assertInstanceOf(ConstructionRequest::class, $report->reportable);
        $this->assertTrue($report->reportable->is($request));
    }

    public function test_les_photos_sont_castees_en_tableau(): void
    {
        $report = Report::factory()->create([
            'photos' => ['a.jpg', 'b.jpg', 'c.jpg'],
        ]);

        $this->assertIsArray($report->fresh()->photos);
        $this->assertCount(3, $report->fresh()->photos);
        $this->assertSame(ReportType::PHOTO, $report->fresh()->type);
    }

    public function test_une_demande_expose_ses_rapports_via_morphmany(): void
    {
        $request = ConstructionRequest::factory()->create();
        Report::factory()->count(2)->create([
            'reportable_type' => ConstructionRequest::class,
            'reportable_id' => $request->id,
        ]);
        // Rapport rattaché à une AUTRE demande (ne doit pas remonter).
        Report::factory()->create();

        $this->assertCount(2, $request->reports);
    }

    public function test_un_rapport_video_porte_une_url(): void
    {
        $report = Report::factory()->video()->create();

        $this->assertSame(ReportType::VIDEO, $report->type);
        $this->assertNotNull($report->video_url);
        $this->assertNull($report->photos);
    }
}
