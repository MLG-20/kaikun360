<?php

namespace Tests\Feature\Admin;

use App\Enums\BookingStatus;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Immo\Models\Property;
use App\Modules\Mobility\Models\MobilityService;
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

    /**
     * F7.2.j — L'écran Mobilité contrôle la conformité : la Resource
     * back-office doit exposer les champs que le catalogue public masque
     * (assurance, chauffeur, gilets) ainsi que le prestataire à joindre.
     */
    public function test_les_vehicules_exposent_les_champs_de_conformite_et_le_prestataire(): void
    {
        $provider = User::factory()->create(['name' => 'Transports Teranga']);
        Vehicle::factory()->create([
            'provider_id' => $provider->id,
            'insurance_ref' => 'ASS-2026-001',
            'driver_identity' => 'Moussa Diop',
        ]);

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/vehicles')
            ->assertOk()
            ->assertJsonPath('data.0.insurance_ref', 'ASS-2026-001')
            ->assertJsonPath('data.0.driver_identity', 'Moussa Diop')
            ->assertJsonPath('data.0.provider.name', 'Transports Teranga');
    }

    /**
     * F7.2.j — Filtre « avec / sans chauffeur » (le cahier des charges traite
     * la mise à disposition de chauffeurs comme une offre à part entière).
     */
    public function test_les_vehicules_sont_filtrables_par_presence_de_chauffeur(): void
    {
        Vehicle::factory()->create(['has_driver' => true]);
        Vehicle::factory()->count(2)->create(['has_driver' => false]);

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/vehicles?driver=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/admin/vehicles?driver=0')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    /**
     * F7.2.j — Trajets programmés : tous statuts (la recherche publique, elle,
     * ne montre que les trajets publiés).
     */
    public function test_les_trajets_programmes_sont_visibles_tous_statuts_et_filtrables(): void
    {
        MobilityService::factory()->create([
            'status' => 'en_attente_validation',
            'departure' => 'Dakar',
            'destination' => 'Saint-Louis',
        ]);
        // Départ/destination épinglés : la factory les tire au sort, et
        // « Saint-Louis » fait partie du tirage — sans cela la recherche libre
        // ci-dessous pourrait remonter les deux trajets.
        MobilityService::factory()->published()->create([
            'departure' => 'Thiès',
            'destination' => 'Mbour',
        ]);

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/mobility-services')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/v1/admin/mobility-services?status=publie')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        // Recherche libre sur le départ ou la destination.
        $this->getJson('/api/v1/admin/mobility-services?q=Saint-Louis')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    /**
     * F7.2.j — Le remplissage d'un départ (« disponibilités » du cahier des
     * charges) : les réservations annulées ne consomment pas de place.
     */
    public function test_le_remplissage_d_un_trajet_ignore_les_reservations_annulees(): void
    {
        $service = MobilityService::factory()->published()->create(['capacity' => 10]);

        $this->bookSeats($service, 4, BookingStatus::CONFIRMEE);
        $this->bookSeats($service, 3, BookingStatus::EN_ATTENTE);
        $this->bookSeats($service, 5, BookingStatus::ANNULEE_CLIENT);

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/mobility-services')
            ->assertOk()
            ->assertJsonPath('data.0.capacity', 10)
            ->assertJsonPath('data.0.seats_taken', 7)
            ->assertJsonPath('data.0.seats_left', 3);
    }

    /**
     * Crée une réservation de `$guests` places sur un trajet, dans l'état donné.
     */
    private function bookSeats(MobilityService $service, int $guests, BookingStatus $status): void
    {
        $service->bookings()->create([
            'reference' => 'BK-'.fake()->unique()->bothify('######'),
            'user_id' => User::factory()->create()->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'guests' => $guests,
            'amount_xof' => $guests * $service->price_xof,
            'status' => $status->value,
        ]);
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
