<?php

namespace Tests\Feature\Security;

use App\Models\Booking;
use App\Models\User;
use App\Modules\Stay\Models\Stay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests B15.2 : le garde « compte vérifié » bloque les actions sensibles
 * (réservation, paiement, publication) tant que ni l'e-mail ni le téléphone
 * n'est vérifié.
 */
class AccountVerifiedGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_compte_non_verifie_ne_peut_pas_reserver_une_nuitee(): void
    {
        $stay = Stay::factory()->create();
        Sanctum::actingAs(User::factory()->unverified()->create());

        $this->postJson("/api/v1/stays/{$stay->id}/bookings", [
            'start_date' => today()->addWeek()->toDateString(),
            'end_date' => today()->addWeek()->addDays(2)->toDateString(),
            'guests' => 1,
        ])->assertStatus(403);
    }

    public function test_un_compte_non_verifie_ne_peut_pas_publier_un_bien(): void
    {
        Sanctum::actingAs(User::factory()->unverified()->create());

        $this->postJson('/api/v1/properties', [])->assertStatus(403);
    }

    public function test_un_compte_non_verifie_ne_peut_pas_initier_un_paiement(): void
    {
        $user = User::factory()->unverified()->create();
        $stay = Stay::factory()->create();
        $booking = Booking::create([
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

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/payments/initiate', ['booking_id' => $booking->id])
            ->assertStatus(403);
    }

    public function test_un_compte_verifie_franchit_le_garde_et_paie(): void
    {
        config()->set('services.paytech.base_url', 'https://paytech.sn/api');
        config()->set('services.paytech.api_secret', 'test-secret');
        config()->set('services.paytech.api_key', 'test-key');
        Http::fake([
            'paytech.sn/*' => Http::response(['success' => 1, 'token' => 'ptx', 'redirect_url' => 'https://paytech.sn/payment/checkout/ptx'], 200),
        ]);

        // Compte vérifié (email_verified_at renseigné par la factory).
        $user = User::factory()->create();
        $stay = Stay::factory()->create();
        $booking = Booking::create([
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

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/payments/initiate', ['booking_id' => $booking->id])
            ->assertCreated();
    }
}
