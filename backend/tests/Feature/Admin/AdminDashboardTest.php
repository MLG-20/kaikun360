<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Immo\Models\Property;
use App\Modules\Manage\Models\Incident;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Pro\Models\Provider;
use App\Modules\Stay\Models\Stay;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests B13.1 : tableau de bord back-office (GET /admin/dashboard).
 *
 * Vérifie l'autorisation (permission consulter:dashboard-admin) et l'exactitude
 * des agrégats (files de validation, activité du jour, revenus, alertes, KPI).
 */
class AdminDashboardTest extends TestCase
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

    public function test_un_utilisateur_ordinaire_n_accede_pas_au_dashboard(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/dashboard')->assertStatus(403);
    }

    public function test_le_dashboard_agrege_correctement_les_indicateurs(): void
    {
        // Nuitée support (sa propriété est publiée via la factory) : sert de
        // parent maîtrisé à l'avis, à l'incident et aux réservations, pour ne pas
        // polluer les files de validation avec des parents auto-générés.
        $stay = Stay::factory()->create();

        // Files de validation en attente.
        Property::factory()->count(2)->create(['status' => 'en_attente_validation']);
        Vehicle::factory()->create(['status' => 'en_attente_validation']);
        TourismExperience::factory()->create(['status' => 'en_attente_validation']);
        Provider::factory()->create(['status' => 'en_attente']);
        Provider::factory()->create(['status' => 'valide']);

        // Alertes (rattachées à la nuitée pour éviter des parents en attente).
        Review::factory()->create([
            'status' => 'en_attente',
            'reviewable_type' => Stay::class,
            'reviewable_id' => $stay->id,
        ]);
        Incident::factory()->create(['status' => 'ouvert', 'property_id' => $stay->property_id]);

        // Revenus : deux réservations actives + une annulée (exclue du revenu).
        $this->booking($stay, 'confirmee', 100_000, 12_000);
        $this->booking($stay, 'terminee', 50_000, 6_000);
        $this->booking($stay, 'annulee_client', 999_999, 99_999);

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.queues.properties_pending', 2)
            ->assertJsonPath('data.queues.vehicles_pending', 1)
            ->assertJsonPath('data.queues.experiences_pending', 1)
            ->assertJsonPath('data.queues.providers_pending', 1)
            ->assertJsonPath('data.today.bookings', 3)
            ->assertJsonPath('data.revenue.gross_volume_xof', 150_000)
            ->assertJsonPath('data.revenue.commission_xof', 18_000)
            ->assertJsonPath('data.alerts.reviews_to_moderate', 1)
            ->assertJsonPath('data.alerts.open_incidents', 1)
            ->assertJsonPath('data.kpi.providers_validated', 1)
            ->assertJsonPath('data.kpi.properties_published', 1)
            ->assertJsonPath('data.kpi.bookings_total', 3);
    }

    /**
     * Fabrique une réservation polymorphe minimale rattachée à une nuitée.
     */
    private function booking(Stay $stay, string $status, int $amount, int $commission): void
    {
        Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => Stay::class,
            'bookable_id' => $stay->id,
            'start_date' => today(),
            'end_date' => today()->addDay(),
            'guests' => 1,
            'amount_xof' => $amount,
            'commission_xof' => $commission,
            'status' => $status,
        ]);
    }
}
