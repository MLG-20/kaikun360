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
 * Tests B15.1 : rate limiting ciblé des endpoints sensibles (auth, paiement).
 */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_login_est_plafonne_a_dix_par_minute(): void
    {
        $payload = ['email' => 'inconnu@example.com', 'password' => 'mauvais'];

        // 10 tentatives autorisées (échouent en 422 anti-énumération)…
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/login', $payload)->assertStatus(422);
        }

        // …la 11e est bloquée par le limiteur.
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(429);
    }

    public function test_l_initiation_de_paiement_est_plafonnee(): void
    {
        config()->set('services.paytech.base_url', 'https://engine-sandbox.pay.tech');
        config()->set('services.paytech.api_key', 'test-key');
        Http::fake([
            'engine-sandbox.pay.tech/*' => Http::response(['id' => 'ptx', 'redirect_url' => 'https://pay.tech/c'], 200),
        ]);

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
            'amount_xof' => 100_000,
            'status' => 'en_attente',
        ]);

        Sanctum::actingAs($user);

        // 15 initiations autorisées par minute.
        for ($i = 0; $i < 15; $i++) {
            $this->postJson('/api/v1/payments/initiate', ['booking_id' => $booking->id])->assertCreated();
        }

        // La 16e est bloquée.
        $this->postJson('/api/v1/payments/initiate', ['booking_id' => $booking->id])->assertStatus(429);
    }
}
