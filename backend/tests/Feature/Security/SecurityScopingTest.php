<?php

namespace Tests\Feature\Security;

use App\Models\Booking;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Immo\Models\Property;
use App\Modules\Stay\Models\Stay;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests B15.5 — revue de sécurité : aucun endpoint ne divulgue de données hors
 * du périmètre autorisé de l'appelant (isolation par propriétaire + cloisonnement
 * des rôles).
 */
class SecurityScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function bookingFor(User $user): Booking
    {
        $stay = Stay::factory()->create();

        return Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => $user->id,
            'bookable_type' => Stay::class,
            'bookable_id' => $stay->id,
            'start_date' => today(),
            'end_date' => today()->addDay(),
            'guests' => 1,
            'amount_xof' => 50_000,
            'status' => 'en_attente',
        ]);
    }

    public function test_bookings_my_ne_renvoie_que_les_reservations_de_l_appelant(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->bookingFor($alice);
        $this->bookingFor($alice);
        $this->bookingFor($bob);

        Sanctum::actingAs($alice);

        $this->getJson('/api/v1/bookings/my')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_properties_mine_ne_renvoie_que_ses_biens(): void
    {
        $alice = User::factory()->create();
        Property::factory()->count(2)->create(['owner_id' => $alice->id]);
        Property::factory()->create(['owner_id' => User::factory()->create()->id]);

        Sanctum::actingAs($alice);

        $this->getJson('/api/v1/properties/mine')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_un_tiers_ne_modifie_pas_le_bien_d_autrui(): void
    {
        $property = Property::factory()->create(['owner_id' => User::factory()->create()->id]);

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/v1/properties/{$property->id}", ['price_xof' => 1])
            ->assertStatus(403);
    }

    public function test_un_client_n_accede_a_aucun_endpoint_back_office(): void
    {
        Sanctum::actingAs($this->clientUser());

        $this->getJson('/api/v1/admin/dashboard')->assertStatus(403);
        $this->getJson('/api/v1/admin/users')->assertStatus(403);
        $this->getJson('/api/v1/admin/payments')->assertStatus(403);
        $this->getJson('/api/v1/admin/queue')->assertStatus(403);
    }

    private function clientUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::CLIENT->value);

        return $user;
    }
}
