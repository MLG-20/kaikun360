<?php

namespace Tests\Feature\Security;

use App\Enums\PaymentStatus;
use App\Models\Media;
use App\Models\Payment;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Immo\Models\Property;
use App\Models\Faq;
use App\Models\Page;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests B15.3 : audit renforcé sur les actions sensibles — modification de prix,
 * validation de paiement, suppression de ressource.
 */
class AuditLogTest extends TestCase
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

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::ADMIN->value);

        return $admin;
    }

    public function test_la_modification_de_prix_est_auditee(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['owner_id' => $owner->id, 'price_xof' => 100_000]);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/properties/{$property->id}", ['price_xof' => 250_000])->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'description' => 'Modification de prix',
            'subject_id' => $property->id,
        ]);
    }

    public function test_la_validation_de_paiement_est_auditee(): void
    {
        // ⚠️ Ce test posait `services.paytech.signing_key`, réglage DISPARU :
        // F8.5 a réécrit l'IPN sur le contrat réel de PayTech (formulaire +
        // `hmac_compute` signé par l'`api_secret`). Il envoyait donc une
        // notification qu'aucun code ne pouvait reconnaître — 401. C'était la
        // JUMELLE de la dette soldée en F8.14 dans `EventNotificationsTest` :
        // les deux tests portaient le même protocole inventé, un seul avait été
        // corrigé. Trouvée en lançant la suite ENTIÈRE (F8.15) — aucune suite
        // partielle ne la traversait.
        config()->set('services.paytech.api_key', self::API_KEY);
        config()->set('services.paytech.api_secret', self::API_SECRET);

        $payment = Payment::create([
            'reference' => 'PAY-'.uniqid(),
            'amount_xof' => 100_000,
            'commission_xof' => 12_000,
            'provider_reference' => 'ptx_audit',
            'status' => PaymentStatus::EN_ATTENTE->value,
        ]);

        $this->webhook($payment)->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'description' => 'Validation de paiement',
            'subject_id' => $payment->id,
        ]);
    }

    public function test_la_suppression_de_media_est_auditee(): void
    {
        // Média orphelin (cible inexistante) : suppression réservée à l'admin.
        $media = Media::factory()->create(['mediable_type' => Property::class, 'mediable_id' => 999999]);

        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/v1/media/{$media->id}")->assertOk();

        $this->assertDatabaseHas('activity_log', ['description' => 'Suppression de média']);
    }

    public function test_les_suppressions_de_contenu_sont_auditees(): void
    {
        $faq = Faq::factory()->create();
        $page = Page::factory()->create();

        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/v1/admin/faqs/{$faq->id}")->assertNoContent();
        $this->deleteJson("/api/v1/admin/pages/{$page->slug}")->assertNoContent();

        $this->assertDatabaseHas('activity_log', ['description' => 'Suppression de FAQ']);
        $this->assertDatabaseHas('activity_log', ['description' => 'Suppression de page']);
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
