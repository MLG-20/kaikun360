<?php

namespace Tests\Feature\Mobility;

use App\Models\User;
use App\Modules\Mobility\Enums\MobilityServiceStatus;
use App\Modules\Mobility\Enums\MobilityServiceType;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests de la couche de données des services de mobilité (phase B7.2).
 */
class MobilityServiceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_service_se_cree_avec_ses_casts(): void
    {
        $service = MobilityService::factory()->create([
            'type' => MobilityServiceType::NAVETTE->value,
            'capacity' => 15,
        ]);
        $service->refresh();

        $this->assertSame(MobilityServiceType::NAVETTE, $service->type);
        $this->assertSame(MobilityServiceStatus::EN_ATTENTE_VALIDATION, $service->status);
        $this->assertSame(15, $service->capacity);
        $this->assertNotNull($service->departure_at);
    }

    public function test_un_service_appartient_a_un_prestataire_et_a_un_vehicule_optionnel(): void
    {
        $provider = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['provider_id' => $provider->id]);
        $service = MobilityService::factory()->create([
            'provider_id' => $provider->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $this->assertTrue($service->provider->is($provider));
        $this->assertTrue($service->vehicle->is($vehicle));
    }

    public function test_le_scope_published_ne_remonte_que_les_publies(): void
    {
        MobilityService::factory()->published()->count(3)->create();
        MobilityService::factory()->create(); // en attente

        $this->assertCount(3, MobilityService::query()->published()->get());
    }
}
