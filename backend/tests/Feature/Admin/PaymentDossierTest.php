<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Pro\Models\Provider;
use App\Modules\Stay\Models\Stay;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests F8.2.d : **dossiers des écrans sensibles** — un paiement, un avis.
 *
 * Le paiement est l'écran le plus sensible du back-office : confirmer à tort
 * crédite une réservation jamais payée, rembourser à tort sort de l'argent
 * réel. Sa fiche doit donc porter les **preuves** (référence PSP, signature,
 * preuve Wave/OM), l'**échéancier complet** de la réservation, et dire au
 * client de l'API ce que le serveur accepterait (`can_confirm`, `can_refund`).
 *
 * L'avis, lui, se modère avec son **contexte** : une plainte isolée au milieu
 * de bons avis n'est pas la troisième plainte identique du mois.
 */
class PaymentDossierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** Agent de terrain : droits d'exploitation, PAS l'accès financier. */
    private function agent(): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);
        $agent->givePermissionTo(AdminPermission::operational());

        return $agent;
    }

    /**
     * Compte habilité aux paiements.
     *
     * `gerer:paiements` relève de la **gouvernance** (CDC §7 : « Agent Kaikun :
     * accès financier limité ») : un agent de terrain ne l'a pas, même pleinement
     * outillé par ailleurs. Les tests de la fiche paiement doivent donc passer
     * par un compte à qui elle a été explicitement déléguée.
     */
    private function financialAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::ADMIN->value);
        $admin->givePermissionTo(AdminPermission::GERER_PAIEMENTS->value);

        return $admin;
    }

    private function stayBooking(): Booking
    {
        $stay = Stay::factory()->create();

        return Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => User::factory()->create(['name' => 'Aïda Sow'])->id,
            'bookable_type' => Stay::class,
            'bookable_id' => $stay->id,
            'start_date' => today(),
            'end_date' => today()->addDays(2),
            'guests' => 2,
            'amount_xof' => 100_000,
            'status' => 'confirmee',
        ]);
    }

    private function payment(Booking $booking, array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'reference' => 'PAY-'.uniqid(),
            'booking_id' => $booking->id,
            'provider' => 'paytech',
            'amount_xof' => 40_000,
            'commission_xof' => 4_000,
            'kind' => 'acompte',
            'status' => 'complete',
            'mode' => 'paytech',
        ], $overrides));
    }

    // --- Paiement ----------------------------------------------------------------

    public function test_un_utilisateur_sans_gerer_paiements_est_refuse(): void
    {
        $payment = $this->payment($this->stayBooking());

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/admin/payments/{$payment->id}")->assertStatus(403);
    }

    public function test_la_fiche_paiement_porte_les_preuves_et_l_echeancier(): void
    {
        $booking = $this->stayBooking();

        $acompte = $this->payment($booking, [
            'amount_xof' => 40_000,
            'provider_reference' => 'PSP-99XZ',
            'signature_verified' => true,
        ]);
        // Un second règlement sur la même réservation : l'acompte ne se lit
        // qu'à côté du reste.
        $this->payment($booking, ['amount_xof' => 30_000, 'kind' => 'solde']);

        Sanctum::actingAs($this->financialAdmin());

        $this->getJson("/api/v1/admin/payments/{$acompte->id}")
            ->assertOk()
            ->assertJsonPath('data.payment.id', $acompte->id)
            // Les éléments de preuve, absents de la Resource publique.
            ->assertJsonPath('data.payment.provider_reference', 'PSP-99XZ')
            ->assertJsonPath('data.payment.signature_verified', true)
            // Un paiement PayTech ne se confirme pas à la main ; encaissé, il se
            // rembourse : c'est le SERVEUR qui le dit, pas l'écran.
            ->assertJsonPath('data.payment.can_confirm', false)
            ->assertJsonPath('data.payment.can_refund', true)
            // La réservation payée, avec son reste dû.
            ->assertJsonPath('data.booking.reference', $booking->reference)
            ->assertJsonPath('data.booking.paid_xof', 70_000)
            ->assertJsonPath('data.booking.remaining_xof', 30_000)
            ->assertJsonPath('data.booking.client.name', 'Aïda Sow')
            // L'échéancier complet, la ligne courante repérée.
            ->assertJsonCount(2, 'data.siblings')
            ->assertJsonPath(
                'data.siblings.*.is_current',
                fn (array $flags) => in_array(true, $flags, true) && in_array(false, $flags, true),
            );
    }

    public function test_la_fiche_dit_qu_un_reglement_manuel_est_confirmable(): void
    {
        $payment = $this->payment($this->stayBooking(), [
            'mode' => 'manuel',
            'status' => 'en_attente',
        ]);

        Sanctum::actingAs($this->financialAdmin());

        $this->getJson("/api/v1/admin/payments/{$payment->id}")
            ->assertOk()
            ->assertJsonPath('data.payment.can_confirm', true)
            // Rien n'est encaissé : il n'y a rien à rembourser.
            ->assertJsonPath('data.payment.can_refund', false);
    }

    public function test_le_journal_porte_le_remboursement_et_son_montant(): void
    {
        // Le remboursement part chez le PSP : on le simule, comme le fait déjà
        // `AdminPaymentTest`. Sans cela l'appel réel échoue et renvoie 502.
        Http::fake(['engine-sandbox.pay.tech/*' => Http::response([], 200)]);

        $payment = $this->payment($this->stayBooking());

        Sanctum::actingAs($this->financialAdmin());

        $this->postJson("/api/v1/admin/payments/{$payment->id}/refund", ['amount_xof' => 15_000])
            ->assertOk();

        $this->getJson("/api/v1/admin/payments/{$payment->id}")
            ->assertOk()
            ->assertJsonPath('data.payment.refunded_amount_xof', 15_000)
            ->assertJsonPath('data.activity.0.description', 'Remboursement de paiement')
            ->assertJsonPath('data.activity.0.properties.amount_xof', 15_000);
    }

    // --- Avis --------------------------------------------------------------------

    public function test_la_fiche_avis_donne_le_contexte_de_la_ressource(): void
    {
        $provider = Provider::factory()->create();

        $review = Review::create([
            'reference' => 'AVI-'.uniqid(),
            'user_id' => User::factory()->create(['name' => 'Moussa Diouf'])->id,
            'reviewable_type' => Provider::class,
            'reviewable_id' => $provider->id,
            'rating' => 1,
            'comment' => 'Prestation annulée sans prévenir.',
            'status' => 'en_attente',
        ]);

        // Deux avis déjà publiés sur le même prestataire, dont un négatif : le
        // contexte qui dit si la plainte est isolée.
        foreach ([[5, 'publie'], [2, 'publie'], [1, 'rejete']] as [$rating, $status]) {
            Review::create([
                'reference' => 'AVI-'.uniqid(),
                'user_id' => User::factory()->create()->id,
                'reviewable_type' => Provider::class,
                'reviewable_id' => $provider->id,
                'rating' => $rating,
                'comment' => 'Avis de contexte',
                'status' => $status,
            ]);
        }

        Sanctum::actingAs($this->agent());

        $this->getJson("/api/v1/admin/reviews/{$review->id}")
            ->assertOk()
            ->assertJsonPath('data.review.id', $review->id)
            ->assertJsonPath('data.review.comment', 'Prestation annulée sans prévenir.')
            ->assertJsonPath('data.review.author.name', 'Moussa Diouf')
            ->assertJsonPath('data.resource.type', 'provider')
            ->assertJsonPath('data.resource.is_provider', true)
            // Seuls les avis PUBLIÉS font contexte (le rejeté est écarté), et
            // l'avis en cours d'examen ne se compte pas lui-même.
            ->assertJsonPath('data.context.published_count', 2)
            ->assertJsonPath('data.context.negative_count', 1)
            ->assertJsonPath('data.context.average', 3.5)
            ->assertJsonCount(2, 'data.context.reviews');
    }
}
