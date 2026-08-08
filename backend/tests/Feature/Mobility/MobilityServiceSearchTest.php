<?php

namespace Tests\Feature\Mobility;

use App\Modules\Mobility\Enums\MobilityServiceType;
use App\Modules\Mobility\Models\MobilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests de la recherche publique des services de mobilité (phase B7.3).
 */
class MobilityServiceSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_recherche_ne_montre_que_les_services_publies(): void
    {
        MobilityService::factory()->published()->count(2)->create();
        MobilityService::factory()->create(); // en attente

        $this->getJson('/api/v1/mobility-services')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_la_recherche_filtre_par_type_et_villes(): void
    {
        MobilityService::factory()->published()->create([
            'type' => MobilityServiceType::NAVETTE->value,
            'departure' => 'Dakar',
            'destination' => 'AIBD',
        ]);
        MobilityService::factory()->published()->create([
            'type' => MobilityServiceType::LIAISON->value,
            'departure' => 'Thiès',
            'destination' => 'Saint-Louis',
        ]);

        $this->getJson('/api/v1/mobility-services?type=navette&departure=Dakar&destination=AIBD')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'navette');
    }

    /**
     * ⚠️ **Dates RELATIVES, jamais absolues.** Ce test posait `2026-07-10` et
     * `2026-07-20` en dur : il fonctionnait à l'écriture, et se serait mis à
     * échouer tout seul le jour où ces dates seraient devenues passées — ce qui
     * est arrivé, révélé par le filtre `aVenir()` de F8.23.a. Un test qui dépend
     * de la date du jour ne dit plus rien du code qu'il couvre.
     */
    public function test_la_recherche_filtre_par_date_de_depart(): void
    {
        $vise = now()->addDays(10)->setTime(8, 0);
        $autre = now()->addDays(20)->setTime(8, 0);

        MobilityService::factory()->published()->create(['departure_at' => $vise]);
        MobilityService::factory()->published()->create(['departure_at' => $autre]);

        $this->getJson('/api/v1/mobility-services?date='.$vise->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
