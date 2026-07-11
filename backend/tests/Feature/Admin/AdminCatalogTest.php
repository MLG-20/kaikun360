<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Immo\Models\Property;
use App\Modules\Mobility\Models\Vehicle;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests B13.7.1 : navigateur back-office des catalogues (biens, véhicules,
 * circuits) — tous statuts, contrairement aux catalogues publics.
 */
class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function agent(): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);

        return $agent;
    }

    public function test_un_utilisateur_sans_acces_back_office_est_refuse(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/properties')->assertStatus(403);
    }

    public function test_les_biens_sont_visibles_tous_statuts_et_filtrables(): void
    {
        Property::factory()->count(2)->create(['status' => 'en_attente_validation']);
        Property::factory()->create(['status' => 'publie']);

        Sanctum::actingAs($this->agent());

        // Tous statuts confondus.
        $this->getJson('/api/v1/admin/properties')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        // Filtre par statut.
        $this->getJson('/api/v1/admin/properties?status=en_attente_validation')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_les_vehicules_sont_visibles_tous_statuts(): void
    {
        Vehicle::factory()->create(['status' => 'en_attente_validation']);
        Vehicle::factory()->create(['status' => 'publie']);

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/vehicles')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/v1/admin/vehicles?status=publie')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_les_circuits_sont_visibles_tous_statuts(): void
    {
        TourismExperience::factory()->create(['status' => 'en_attente_validation']);
        TourismExperience::factory()->create(['status' => 'publie']);

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/experiences')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }
}
