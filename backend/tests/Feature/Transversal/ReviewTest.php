<?php

namespace Tests\Feature\Transversal;

use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use App\Modules\Mobility\Models\Vehicle;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests transversaux B12.2 : avis polymorphes. Règle centrale du cahier des
 * charges — seul un utilisateur ayant consommé la ressource (réservation
 * terminée) peut déposer un avis ; l'avis part en modération (`en_attente`).
 */
class ReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** Marque une réservation terminée du user sur le véhicule (= consommation). */
    private function consume(User $user, Vehicle $vehicle, string $status = 'terminee'): void
    {
        Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => $user->id,
            'bookable_type' => Vehicle::class,
            'bookable_id' => $vehicle->id,
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->subDays(5)->toDateString(),
            'guests' => 1,
            'amount_xof' => 100_000,
            'status' => $status,
        ]);
    }

    public function test_le_consommateur_depose_un_avis_en_attente(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $this->consume($user, $vehicle);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/reviews', [
            'reviewable_type' => 'vehicle',
            'reviewable_id' => $vehicle->id,
            'rating' => 5,
            'comment' => 'Excellent véhicule.',
        ])->assertCreated()->assertJsonPath('data.review.status', 'en_attente');
    }

    public function test_sans_reservation_terminee_pas_d_avis(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        // Réservation seulement en cours : pas de consommation avérée.
        $this->consume($user, $vehicle, 'en_cours');

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/reviews', [
            'reviewable_type' => 'vehicle',
            'reviewable_id' => $vehicle->id,
            'rating' => 4,
        ])->assertStatus(403);
    }

    public function test_un_seul_avis_par_ressource(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $this->consume($user, $vehicle);
        Review::factory()->create([
            'user_id' => $user->id,
            'reviewable_type' => Vehicle::class,
            'reviewable_id' => $vehicle->id,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/reviews', [
            'reviewable_type' => 'vehicle',
            'reviewable_id' => $vehicle->id,
            'rating' => 3,
        ])->assertStatus(422)->assertJsonValidationErrors('reviewable_id');
    }

    public function test_la_note_doit_etre_entre_1_et_5(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $this->consume($user, $vehicle);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/reviews', [
            'reviewable_type' => 'vehicle',
            'reviewable_id' => $vehicle->id,
            'rating' => 9,
        ])->assertStatus(422)->assertJsonValidationErrors('rating');
    }

    public function test_la_liste_publique_ne_montre_que_les_avis_publies(): void
    {
        $vehicle = Vehicle::factory()->create();
        Review::factory()->published()->create([
            'reviewable_type' => Vehicle::class,
            'reviewable_id' => $vehicle->id,
            'rating' => 5,
        ]);
        Review::factory()->published()->create([
            'reviewable_type' => Vehicle::class,
            'reviewable_id' => $vehicle->id,
            'rating' => 4,
        ]);
        Review::factory()->create([ // en attente → exclu
            'reviewable_type' => Vehicle::class,
            'reviewable_id' => $vehicle->id,
            'rating' => 1,
        ]);

        $this->getJson("/api/v1/reviews?reviewable_type=vehicle&reviewable_id={$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('data.summary.count', 2)
            ->assertJsonPath('data.summary.average', 4.5);
    }
}
