<?php

namespace Tests\Feature\Immo;

use App\Models\Region;
use App\Modules\Immo\Enums\PropertyType;
use App\Modules\Immo\Models\Property;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\SenegalGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests du catalogue public des biens (phase B2.2).
 *
 * Point capital : un visiteur ne voit JAMAIS un bien non publié.
 */
class PropertyCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SenegalGeographySeeder::class, CommunesSeeder::class]);
    }

    public function test_le_catalogue_ne_montre_que_les_biens_publies(): void
    {
        Property::factory()->published()->count(2)->create();
        Property::factory()->pending()->count(3)->create();

        $this->getJson('/api/v1/properties')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data', 'links', 'meta']); // enveloppe paginée standard
    }

    public function test_filtre_par_type(): void
    {
        Property::factory()->published()->create(['type' => PropertyType::VILLA->value]);
        Property::factory()->published()->create(['type' => PropertyType::TERRAIN->value]);

        $res = $this->getJson('/api/v1/properties?type=villa')->assertOk();

        $res->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'villa');
    }

    public function test_filtre_par_region(): void
    {
        $dakar = Region::where('name', 'Dakar')->first();
        $thies = Region::where('name', 'Thiès')->first();

        Property::factory()->published()->create(['region_id' => $dakar->id]);
        Property::factory()->published()->create(['region_id' => $thies->id]);

        $this->getJson("/api/v1/properties?region_id={$dakar->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.location.region', 'Dakar');
    }

    public function test_filtre_par_fourchette_de_prix(): void
    {
        Property::factory()->published()->create(['price_xof' => 10_000_000]);
        Property::factory()->published()->create(['price_xof' => 100_000_000]);

        $this->getJson('/api/v1/properties?price_min=50000000')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.price_xof', 100_000_000);
    }

    public function test_detail_d_un_bien_publie(): void
    {
        $property = Property::factory()->published()->create();

        $this->getJson("/api/v1/properties/{$property->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $property->id);
    }

    public function test_detail_d_un_bien_non_publie_renvoie_404(): void
    {
        $property = Property::factory()->pending()->create();

        $this->getJson("/api/v1/properties/{$property->id}")
            ->assertNotFound();
    }
}
