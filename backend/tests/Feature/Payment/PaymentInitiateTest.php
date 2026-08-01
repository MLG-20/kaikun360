<?php

namespace Tests\Feature\Payment;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Modules\Stay\Models\Stay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests B14.2 : initiation de paiement (POST /payments/initiate).
 */
class PaymentInitiateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.paytech.base_url', 'https://paytech.sn/api');
        config()->set('services.paytech.api_key', 'test-key');
        config()->set('services.paytech.api_secret', 'test-secret');
    }

    private function fakePaytechOk(): void
    {
Http::fake([
            'paytech.sn/*' => Http::response([
                'success' => 1,
                'token' => 'ptx_ok',
                'redirect_url' => 'https://paytech.sn/payment/checkout/ptx_ok',
            ], 200),
        ]);
    }

    private function bookingFor(User $user, array $overrides = []): Booking
    {
        $stay = Stay::factory()->create();

        return Booking::create(array_merge([
            'reference' => 'BK-'.uniqid(),
            'user_id' => $user->id,
            'bookable_type' => Stay::class,
            'bookable_id' => $stay->id,
            'start_date' => today(),
            'end_date' => today()->addDay(),
            'guests' => 1,
            'amount_xof' => 100_000,
            'commission_xof' => 12_000,
            'status' => 'en_attente',
        ], $overrides));
    }

    public function test_l_authentification_est_requise(): void
    {
        $this->postJson('/api/v1/payments/initiate', ['booking_id' => 1])->assertStatus(401);
    }

    public function test_le_titulaire_initie_le_paiement(): void
    {
        $this->fakePaytechOk();
        $user = User::factory()->create();
        $booking = $this->bookingFor($user);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/payments/initiate', ['booking_id' => $booking->id])
            ->assertCreated()
            ->assertJsonPath('data.redirect_url', 'https://paytech.sn/payment/checkout/ptx_ok')
            ->assertJsonPath('data.payment.status', 'en_attente')
            ->assertJsonPath('data.payment.amount_xof', 100_000);

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'status' => 'en_attente',
            'provider_reference' => 'ptx_ok',
            'commission_xof' => 12_000,
        ]);
    }

    public function test_un_tiers_ne_paie_pas_la_reservation_d_autrui(): void
    {
        $this->fakePaytechOk();
        $booking = $this->bookingFor(User::factory()->create());

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/payments/initiate', ['booking_id' => $booking->id])
            ->assertStatus(403);
    }

    public function test_une_reservation_deja_payee_est_refusee(): void
    {
        $this->fakePaytechOk();
        $user = User::factory()->create();
        $booking = $this->bookingFor($user);
        Payment::create([
            'reference' => 'PAY-DONE',
            'booking_id' => $booking->id,
            'amount_xof' => 100_000,
            'status' => PaymentStatus::COMPLETE->value,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/payments/initiate', ['booking_id' => $booking->id])
            ->assertStatus(422);
    }

    public function test_une_reservation_annulee_est_refusee(): void
    {
        $this->fakePaytechOk();
        $user = User::factory()->create();
        $booking = $this->bookingFor($user, ['status' => 'annulee_client']);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/payments/initiate', ['booking_id' => $booking->id])
            ->assertStatus(422);
    }

    public function test_une_panne_du_psp_renvoie_502(): void
    {
        Http::fake(['paytech.sn/*' => Http::response([], 500)]);
        $user = User::factory()->create();
        $booking = $this->bookingFor($user);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/payments/initiate', ['booking_id' => $booking->id])
            ->assertStatus(502);
    }
}
