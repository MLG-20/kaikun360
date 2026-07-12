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
        config()->set('services.paytech.signing_key', 'whsec');
        $payment = Payment::create([
            'reference' => 'PAY-'.uniqid(),
            'amount_xof' => 100_000,
            'commission_xof' => 12_000,
            'provider_reference' => 'ptx_audit',
            'status' => PaymentStatus::EN_ATTENTE->value,
        ]);

        $this->webhook(['id' => 'ptx_audit', 'status' => 'COMPLETED', 'amount' => 100_000])->assertOk();

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
