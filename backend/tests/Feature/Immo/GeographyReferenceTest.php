<?php

namespace Tests\Feature\Immo;

use App\Models\Commune;
use App\Models\Department;
use App\Models\Region;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\SenegalGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests du référentiel géographique du Sénégal (phase B2.1).
 */
class GeographyReferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SenegalGeographySeeder::class, CommunesSeeder::class]);
    }

    public function test_quatorze_regions_et_quarante_six_departements(): void
    {
        $this->assertSame(14, Region::count());
        $this->assertSame(46, Department::count());
    }

    public function test_le_departement_de_dakar_a_dix_neuf_communes(): void
    {
        $dakar = Department::whereHas('region', fn ($q) => $q->where('name', 'Dakar'))
            ->where('name', 'Dakar')
            ->first();

        $this->assertNotNull($dakar);
        $this->assertSame(19, $dakar->communes()->count());
    }

    public function test_la_hierarchie_commune_departement_region_est_navigable(): void
    {
        $commune = Commune::query()->first();

        $this->assertNotNull($commune);
        $this->assertNotNull($commune->department);
        $this->assertNotNull($commune->department->region);
    }
}
