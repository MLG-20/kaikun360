<?php

namespace Tests\Feature\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests B14.4 : supervision et remboursement des paiements (back-office).
 */
class AdminPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config()->set('services.paytech.base_url', 'https://paytech.sn/api');
        config()->set('services.paytech.api_key', 'test-key');
        config()->set('services.paytech.api_secret', 'test-secret');
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function payment(array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'reference' => 'PAY-'.uniqid(),
            'amount_xof' => 100_000,
            'commission_xof' => 12_000,
            'provider_reference' => 'ptx_'.uniqid(),
            'status' => PaymentStatus::COMPLETE->value,
        ], $overrides));
    }

    public function test_l_agent_sans_gerer_paiements_est_refuse(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::AGENT_KAIKUN->value));

        $this->getJson('/api/v1/admin/payments')->assertStatus(403);
    }

    public function test_l_admin_liste_et_filtre_les_paiements(): void
    {
        $this->payment(['status' => PaymentStatus::COMPLETE->value]);
        $this->payment(['status' => PaymentStatus::REFUSE->value]);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->getJson('/api/v1/admin/payments')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/v1/admin/payments?status=complete')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_l_admin_rembourse_un_paiement_encaisse(): void
    {
        Http::fake(['paytech.sn/*' => Http::response(['success' => 1], 200)]);
        $payment = $this->payment();

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->postJson("/api/v1/admin/payments/{$payment->id}/refund")
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'rembourse');

        $this->assertSame(PaymentStatus::REMBOURSE, $payment->fresh()->status);
        $this->assertSame($payment->amount_xof, $payment->fresh()->meta['refunded_amount_xof']);
    }

    public function test_on_ne_rembourse_pas_un_paiement_non_encaisse(): void
    {
        $payment = $this->payment(['status' => PaymentStatus::EN_ATTENTE->value]);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->postJson("/api/v1/admin/payments/{$payment->id}/refund")
            ->assertStatus(422);
    }

    /**
     * F8.5 — PayTech ne rembourse que la TOTALITÉ : sa route ne prend pas de
     * montant. Proposer un remboursement partiel afficherait « remboursé » pour
     * une opération que le PSP n'exécutera jamais.
     */
    public function test_le_remboursement_partiel_est_refuse(): void
    {
        $payment = $this->payment();

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->postJson("/api/v1/admin/payments/{$payment->id}/refund", ['amount_xof' => 40_000])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount_xof']);

        $this->assertSame(PaymentStatus::COMPLETE, $payment->fresh()->status);
    }

    public function test_le_montant_rembourse_ne_depasse_pas_le_paye(): void
    {
        $payment = $this->payment();

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->postJson("/api/v1/admin/payments/{$payment->id}/refund", ['amount_xof' => 999_999])
            ->assertStatus(422);
    }

    public function test_un_echec_psp_renvoie_502(): void
    {
        Http::fake(['paytech.sn/*' => Http::response([], 500)]);
        $payment = $this->payment();

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->postJson("/api/v1/admin/payments/{$payment->id}/refund")
            ->assertStatus(502);

        // Le paiement reste encaissé (pas de faux remboursement).
        $this->assertSame(PaymentStatus::COMPLETE, $payment->fresh()->status);
    }
}
