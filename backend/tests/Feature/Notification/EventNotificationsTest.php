<?php

namespace Tests\Feature\Notification;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
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

    /** Clés PayTech du test : la signature de l'IPN se calcule avec elles. */
    private const API_KEY = 'test_api_key';
    private const API_SECRET = 'test_api_secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function agent(): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);
        $agent->givePermissionTo(AdminPermission::operational()); // F7.1.b : agent pleinement outillé (droits désormais délégués, plus portés par le rôle)

        return $agent;
    }

    public function test_le_paiement_encaisse_confirme_la_reservation_au_client(): void
    {
        Notification::fake();
        // ⚠️ Ce test posait `services.paytech.signing_key`, un réglage qui
        // N'EXISTE PLUS : F8.5 a réécrit l'IPN sur le contrat réel de PayTech
        // (formulaire + `hmac_compute` signé par l'`api_secret`). Il envoyait
        // donc une notification qu'aucun code n'aurait reconnue — 401 — et
        // c'était la dernière trace de l'ancien protocole inventé.
        config()->set('services.paytech.api_key', self::API_KEY);
        config()->set('services.paytech.api_secret', self::API_SECRET);

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

        $this->webhook($payment)->assertOk();

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

    /**
     * Notification d'encaissement PayTech, telle que le PSP l'envoie RÉELLEMENT
     * (contrat rétabli en F8.5) : un FORMULAIRE, un `type_event`, et la preuve
     * d'authenticité dans le corps — `hmac_compute` = HMAC-SHA256 de
     * `{final_item_price}|{ref_command}|{api_key}`, clé = `api_secret`.
     *
     * ⚠️ `final_item_price` diffère volontairement du montant commandé : en
     * sandbox, PayTech ne débite qu'un montant aléatoire. C'est `ref_command`
     * qui identifie le règlement, jamais le montant reçu.
     */
    private function webhook(Payment $payment): TestResponse
    {
        $finalPrice = 127;

        $payload = [
            'type_event' => 'sale_complete',
            'ref_command' => $payment->reference,
            'token' => $payment->provider_reference,
            'item_name' => 'Kaikun 360',
            'item_price' => $payment->amount_xof,
            'initial_item_price' => $payment->amount_xof,
            'final_item_price' => $finalPrice,
            'currency' => 'XOF',
            'command_name' => 'Kaikun 360',
            'payment_method' => 'Orange Money',
            'client_phone' => '771234567',
            'env' => 'test',
            'hmac_compute' => hash_hmac(
                'sha256',
                implode('|', [$finalPrice, $payment->reference, self::API_KEY]),
                self::API_SECRET,
            ),
        ];

        return $this->post('/api/v1/payments/webhook', $payload, ['Accept' => 'application/json']);
    }
}
