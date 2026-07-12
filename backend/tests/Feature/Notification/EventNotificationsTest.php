<?php

namespace Tests\Feature\Notification;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Stay\Models\Stay;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\DocumentRequiredNotification;
use App\Notifications\QuoteReceivedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests B16.2 : templates de notification déclenchés par événement
 * (confirmation de réservation, nouveau devis, document à fournir).
 */
class EventNotificationsTest extends TestCase
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

    public function test_le_paiement_encaisse_confirme_la_reservation_au_client(): void
    {
        Notification::fake();
        config()->set('services.paytech.signing_key', 'whsec');

        $client = User::factory()->create();
        $stay = Stay::factory()->create();
        $booking = Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => $client->id,
            'bookable_type' => Stay::class,
            'bookable_id' => $stay->id,
            'start_date' => today(),
            'end_date' => today()->addDay(),
            'guests' => 1,
            'amount_xof' => 100_000,
            'status' => 'en_attente',
        ]);
        $payment = Payment::create([
            'reference' => 'PAY-'.uniqid(),
            'booking_id' => $booking->id,
            'amount_xof' => 100_000,
            'provider_reference' => 'ptx_evt',
            'status' => PaymentStatus::EN_ATTENTE->value,
        ]);

        $this->webhook(['id' => 'ptx_evt', 'status' => 'COMPLETED', 'amount' => 100_000])->assertOk();

        Notification::assertSentTo($client, BookingConfirmedNotification::class);
    }

    public function test_un_devis_propose_notifie_le_demandeur(): void
    {
        Notification::fake();

        $client = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['user_id' => $client->id]);

        Sanctum::actingAs($this->agent());

        $this->postJson("/api/v1/requests/{$serviceRequest->id}/quotes", ['amount_xof' => 250_000])
            ->assertCreated();

        Notification::assertSentTo($client, QuoteReceivedNotification::class);
    }

    public function test_le_back_office_demande_un_document_a_un_utilisateur(): void
    {
        Notification::fake();

        $target = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::ADMIN->value);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/users/{$target->id}/request-document", [
            'document_type' => 'CNI recto-verso',
        ])->assertOk();

        Notification::assertSentTo($target, DocumentRequiredNotification::class);
    }

    private function webhook(array $payload): TestResponse
    {
        $raw = json_encode($payload);
        $sig = hash_hmac('sha256', $raw, 'whsec');

        return $this->call(
            'POST',
            '/api/v1/payments/webhook',
            [], [], [],
            ['HTTP_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            $raw,
        );
    }
}
