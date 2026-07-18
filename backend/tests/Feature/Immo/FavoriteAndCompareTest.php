<?php

namespace Tests\Feature\Immo;

use App\Modules\Immo\Models\Property;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\SenegalGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests de la comparaison de biens (phase B2.5).
 *
 * NB : les favoris, autrefois testés ici, sont devenus POLYMORPHES (tous univers)
 * et sont désormais couverts par `Tests\Feature\Transversal\FavoriteTest`.
 */
class FavoriteAndCompareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SenegalGeographySeeder::class, CommunesSeeder::class]);
    }

    public function test_la_comparaison_ne_renvoie_que_les_biens_publies(): void
    {
        $p1 = Property::factory()->published()->create();
        $p2 = Property::factory()->published()->create();
        $p3 = Property::factory()->pending()->create();

        $this->getJson("/api/v1/properties/compare?ids={$p1->id},{$p2->id},{$p3->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data'); // le bien non publié est exclu
    }
}
