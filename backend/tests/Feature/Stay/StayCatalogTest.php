<?php

namespace Tests\Feature\Stay;

use App\Modules\Immo\Models\Property;
use App\Modules\Stay\Models\Stay;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\SenegalGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests du catalogue public des nuitées (phase B3.2).
 */
class StayCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SenegalGeographySeeder::class, CommunesSeeder::class]);
    }

    public function test_le_catalogue_ne_montre_que_les_nuitees_reservables(): void
    {
        // Réservable.
        Stay::factory()->create();
        // Bien non publié.
        Stay::factory()->create(['property_id' => Property::factory()->pending()->create()->id]);
        // Nuitée désactivée.
        Stay::factory()->inactive()->create();

        $this->getJson('/api/v1/stays')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_filtre_par_capacite(): void
    {
        Stay::factory()->create(['capacity' => 2]);
        Stay::factory()->create(['capacity' => 6]);

        $this->getJson('/api/v1/stays?capacity=5')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.capacity', 6);
    }

    public function test_detail_d_une_nuitee_reservable_inclut_le_bien(): void
    {
        $stay = Stay::factory()->create();

        $this->getJson("/api/v1/stays/{$stay->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $stay->id)
            ->assertJsonPath('data.property.id', $stay->property_id);
    }

    public function test_detail_d_une_nuitee_non_reservable_renvoie_404(): void
    {
        $stay = Stay::factory()->inactive()->create();

        $this->getJson("/api/v1/stays/{$stay->id}")->assertNotFound();
    }
}
