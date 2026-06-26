<?php

namespace Tests\Feature\Immo;

use App\Models\User;
use App\Modules\Immo\Enums\PropertyStatus;
use App\Modules\Immo\Models\Property;
use App\Modules\Immo\Models\PropertyDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests du socle de données du module Immo (phase B2.1).
 */
class PropertyModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_bien_appartient_a_un_proprietaire(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['owner_id' => $owner->id]);

        $this->assertTrue($property->owner->is($owner));
    }

    public function test_le_statut_par_defaut_est_en_attente_de_validation(): void
    {
        $property = Property::factory()->create();

        $this->assertSame(PropertyStatus::EN_ATTENTE_VALIDATION, $property->status);
    }

    public function test_un_bien_possede_des_documents(): void
    {
        $property = Property::factory()->create();
        PropertyDocument::create([
            'property_id' => $property->id,
            'type' => 'titre_foncier',
            'disk' => 'local',
            'path' => 'properties/'.$property->id.'/tf.pdf',
            'original_name' => 'tf.pdf',
        ]);

        $this->assertCount(1, $property->documents);
    }

    public function test_le_scope_published_ne_renvoie_que_les_biens_publies(): void
    {
        Property::factory()->published()->count(2)->create();
        Property::factory()->pending()->count(3)->create();

        $this->assertSame(2, Property::published()->count());
    }
}
