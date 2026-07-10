<?php

namespace Tests\Feature\Transversal;

use App\Models\Booking;
use App\Models\Quote;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Mobility\Models\Vehicle;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests transversaux B11.3 : devis (proposition/consultation/réponse) et
 * réservations (horodatage d'annulation automatique, /bookings/my).
 */
class QuoteAndBookingTest extends TestCase
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

    private function bookingFor(User $user, string $status = 'en_attente'): Booking
    {
        $vehicle = Vehicle::factory()->create();

        return Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => $user->id,
            'bookable_type' => Vehicle::class,
            'bookable_id' => $vehicle->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->addDays(2)->toDateString(),
            'guests' => 1,
            'amount_xof' => 100_000,
            'status' => $status,
        ]);
    }

    // --- Devis ----------------------------------------------------------------

    public function test_un_agent_propose_un_devis_mais_pas_un_client(): void
    {
        $request = ServiceRequest::factory()->create();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v1/requests/{$request->id}/quotes", ['amount_xof' => 500_000])
            ->assertStatus(403);

        Sanctum::actingAs($this->agent());
        $this->postJson("/api/v1/requests/{$request->id}/quotes", ['amount_xof' => 500_000])
            ->assertCreated()
            ->assertJsonPath('data.quote.status', 'envoye');
    }

    public function test_le_demandeur_consulte_son_devis_pas_un_tiers(): void
    {
        $owner = User::factory()->create();
        $request = ServiceRequest::factory()->create(['user_id' => $owner->id]);
        $quote = Quote::factory()->create(['request_id' => $request->id]);

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/quotes/{$quote->id}")->assertOk();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/quotes/{$quote->id}")->assertStatus(403);
    }

    public function test_le_demandeur_accepte_son_devis(): void
    {
        $owner = User::factory()->create();
        $request = ServiceRequest::factory()->create(['user_id' => $owner->id]);
        $quote = Quote::factory()->create(['request_id' => $request->id]); // envoye

        Sanctum::actingAs($owner);
        $this->patchJson("/api/v1/quotes/{$quote->id}", ['status' => 'accepte'])
            ->assertOk()
            ->assertJsonPath('data.quote.status', 'accepte');
    }

    public function test_un_tiers_ne_repond_pas_au_devis(): void
    {
        $quote = Quote::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/v1/quotes/{$quote->id}", ['status' => 'refuse'])->assertStatus(403);
    }

    public function test_on_ne_repond_pas_a_un_devis_deja_tranche(): void
    {
        $owner = User::factory()->create();
        $request = ServiceRequest::factory()->create(['user_id' => $owner->id]);
        $quote = Quote::factory()->create(['request_id' => $request->id, 'status' => 'accepte']);

        Sanctum::actingAs($owner);
        $this->patchJson("/api/v1/quotes/{$quote->id}", ['status' => 'refuse'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    // --- Réservations ---------------------------------------------------------

    public function test_l_annulation_fige_automatiquement_le_horodatage(): void
    {
        $booking = $this->bookingFor(User::factory()->create());
        $this->assertNull($booking->cancelled_at);

        $booking->update(['status' => 'annulee_client']);

        $this->assertNotNull($booking->fresh()->cancelled_at);
    }

    public function test_bookings_my_ne_liste_que_mes_reservations(): void
    {
        $user = User::factory()->create();
        $this->bookingFor($user);
        $this->bookingFor($user);
        $this->bookingFor(User::factory()->create()); // autre utilisateur

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/bookings/my')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
