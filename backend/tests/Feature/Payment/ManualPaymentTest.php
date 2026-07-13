<?php

namespace Tests\Feature\Payment;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Stay\Models\Stay;
use App\Notifications\BookingConfirmedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests B20 : paiement manuel (Phase 1 du cahier des charges).
 *
 * Le client initie un règlement Wave/Orange Money hors PSP, un admin le confirme
 * dans le back-office. Aucun appel PayTech ne doit partir en mode manuel.
 */
class ManualPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config()->set('services.paytech.base_url', 'https://engine-sandbox.pay.tech');
        config()->set('services.paytech.api_key', 'test-key');
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
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

    public function test_l_initiation_manuelle_ne_contacte_pas_le_psp(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $booking = $this->bookingFor($user);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/payments/initiate', ['booking_id' => $booking->id, 'mode' => 'manuel'])
            ->assertCreated()
            ->assertJsonPath('data.payment.status', 'en_attente')
            ->assertJsonPath('data.payment.mode', 'manuel')
            ->assertJsonPath('data.instructions.reference', fn ($ref) => str_starts_with((string) $ref, 'PAY-'))
            ->assertJsonPath('data.instructions.method', 'Wave / Orange Money');

        // Aucune requête HTTP sortante : le PSP n'a pas été sollicité.
        Http::assertNothingSent();

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'mode' => 'manuel',
            'provider' => 'manuel',
            'status' => 'en_attente',
        ]);
    }

    public function test_l_admin_confirme_un_paiement_manuel_et_confirme_la_reservation(): void
    {
        Notification::fake();
        $client = User::factory()->create();
        $booking = $this->bookingFor($client);
        $payment = Payment::create([
            'reference' => 'PAY-MANU1',
            'booking_id' => $booking->id,
            'provider' => 'manuel',
            'amount_xof' => 100_000,
            'commission_xof' => 12_000,
            'status' => PaymentStatus::EN_ATTENTE->value,
            'mode' => 'manuel',
        ]);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->postJson("/api/v1/admin/payments/{$payment->id}/confirm", ['provider_reference' => 'WAVE-TXN-42'])
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'complete');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'complete',
            'provider_reference' => 'WAVE-TXN-42',
        ]);
        $this->assertSame(BookingStatus::CONFIRMEE->value, $booking->fresh()->status->value);
        Notification::assertSentTo($client, BookingConfirmedNotification::class);
    }

    public function test_un_agent_sans_permission_ne_confirme_pas(): void
    {
        $payment = Payment::create([
            'reference' => 'PAY-MANU2',
            'provider' => 'manuel',
            'amount_xof' => 50_000,
            'status' => PaymentStatus::EN_ATTENTE->value,
            'mode' => 'manuel',
        ]);

        Sanctum::actingAs($this->withRole(UserRole::AGENT_KAIKUN->value));

        $this->postJson("/api/v1/admin/payments/{$payment->id}/confirm")->assertStatus(403);
    }

    public function test_un_paiement_deja_confirme_ne_se_reconfirme_pas(): void
    {
        $payment = Payment::create([
            'reference' => 'PAY-MANU3',
            'provider' => 'manuel',
            'amount_xof' => 50_000,
            'status' => PaymentStatus::COMPLETE->value,
            'mode' => 'manuel',
        ]);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->postJson("/api/v1/admin/payments/{$payment->id}/confirm")->assertStatus(422);
    }

    public function test_un_paiement_paytech_ne_se_confirme_pas_manuellement(): void
    {
        $payment = Payment::create([
            'reference' => 'PAY-PT1',
            'provider' => 'paytech',
            'amount_xof' => 50_000,
            'status' => PaymentStatus::EN_ATTENTE->value,
            'mode' => 'paytech',
        ]);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->postJson("/api/v1/admin/payments/{$payment->id}/confirm")->assertStatus(422);
    }
}
