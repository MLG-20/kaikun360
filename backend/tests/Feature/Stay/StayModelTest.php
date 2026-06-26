<?php

namespace Tests\Feature\Stay;

use App\Modules\Immo\Models\Property;
use App\Modules\Stay\Models\Stay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests du socle de données du module Stay (phase B3.1).
 */
class StayModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_nuitee_appartient_a_un_bien(): void
    {
        $property = Property::factory()->published()->create();
        $stay = Stay::factory()->create(['property_id' => $property->id]);

        $this->assertTrue($stay->property->is($property));
    }

    public function test_les_champs_json_et_booleens_sont_castes(): void
    {
        $stay = Stay::factory()->create(['rules' => ['non_fumeur' => true]]);

        $this->assertIsArray($stay->rules);
        $this->assertIsBool($stay->is_active);
    }

    public function test_le_scope_bookable_exige_bien_publie_et_actif(): void
    {
        // Réservable : bien publié + actif.
        Stay::factory()->create();

        // Non réservable : bien en attente.
        $pending = Property::factory()->pending()->create();
        Stay::factory()->create(['property_id' => $pending->id]);

        // Non réservable : nuitée désactivée (sur bien publié).
        Stay::factory()->inactive()->create();

        $this->assertSame(1, Stay::bookable()->count());
    }
}
