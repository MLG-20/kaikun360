<?php

namespace Tests\Feature\Mobility;

use App\Models\Booking;
use App\Models\User;
use App\Modules\Mobility\Enums\VehicleStatus;
use App\Modules\Mobility\Enums\VehicleType;
use App\Modules\Mobility\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests de la couche de données des véhicules (phase B7.1) : casts, relation
 * prestataire, réservations polymorphes, scope de publication et helper de type.
 */
class VehicleModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_vehicule_se_cree_avec_ses_casts(): void
    {
        $vehicle = Vehicle::factory()->create(['capacity' => 9, 'has_driver' => true]);
        $vehicle->refresh();

        $this->assertInstanceOf(VehicleType::class, $vehicle->type);
        $this->assertSame(VehicleStatus::EN_ATTENTE_VALIDATION, $vehicle->status);
        $this->assertSame(9, $vehicle->capacity);
        $this->assertTrue($vehicle->has_driver);
    }

    public function test_un_vehicule_appartient_a_un_prestataire(): void
    {
        $provider = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['provider_id' => $provider->id]);

        $this->assertTrue($vehicle->provider->is($provider));
    }

    public function test_un_vehicule_a_des_reservations_polymorphes(): void
    {
        $vehicle = Vehicle::factory()->create();
        Booking::create([
            'reference' => 'BK-VEH-1',
            'user_id' => User::factory()->create()->id,
            'bookable_type' => Vehicle::class,
            'bookable_id' => $vehicle->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-03',
            'guests' => 2,
            'amount_xof' => 100_000,
            'status' => 'en_attente',
        ]);

        $this->assertCount(1, $vehicle->bookings);
        $this->assertInstanceOf(Vehicle::class, $vehicle->bookings->first()->bookable);
    }

    public function test_le_scope_published_ne_remonte_que_les_publies(): void
    {
        Vehicle::factory()->published()->count(2)->create();
        Vehicle::factory()->create(); // en attente

        $this->assertCount(2, Vehicle::query()->published()->get());
    }

    public function test_le_type_distingue_motorise_et_pirogue(): void
    {
        $this->assertTrue(VehicleType::BUS->isMotorized());
        $this->assertFalse(VehicleType::PIROGUE->isMotorized());
    }
}
