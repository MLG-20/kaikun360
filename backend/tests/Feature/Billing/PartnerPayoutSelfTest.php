<?php

namespace Tests\Feature\Billing;

use App\Enums\BookingStatus;
use App\Enums\PartnerDueStatus;
use App\Models\Booking;
use App\Models\PartnerPayout;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Immo\Models\Property;
use App\Modules\Stay\Models\Stay;
use App\Support\Payouts\PartnerDueRegistrar;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SenegalGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * « Mes reversements » — self-service, ouvert au partenaire lui-même.
 *
 * Le registre existait depuis F8.16.a mais seulement côté back-office
 * (`gerer:paiements`) : un propriétaire ou un prestataire ne pouvait rien
 * voir de ce qu'on lui devait. Ces routes ouvrent la MÊME donnée, scopée à
 * l'utilisateur connecté, sans aucune action.
 */
class PartnerPayoutSelfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, SenegalGeographySeeder::class, CommunesSeeder::class]);
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function nuiteeTerminee(User $proprietaire, int $montant = 200_000, int $commission = 24_000): Booking
    {
        $property = Property::factory()->published()->create(['owner_id' => $proprietaire->id]);
        $stay = Stay::factory()->create(['property_id' => $property->id]);

        return Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => Stay::class,
            'bookable_id' => $stay->id,
            'start_date' => now()->subDays(32)->toDateString(),
            'end_date' => now()->subDays(30)->toDateString(),
            'amount_xof' => $montant,
            'commission_xof' => $commission,
            'status' => BookingStatus::TERMINEE->value,
        ]);
    }

    public function test_sans_authentification_l_acces_est_refuse(): void
    {
        $this->getJson('/api/v1/reversements/mine')->assertStatus(401);
        $this->getJson('/api/v1/reversements/mine/payouts')->assertStatus(401);
    }

    public function test_un_proprietaire_ne_voit_que_ses_propres_dettes(): void
    {
        $hote = $this->withRole(UserRole::PROPRIETAIRE->value);
        $autre = $this->withRole(UserRole::PROPRIETAIRE->value);

        $registrar = app(PartnerDueRegistrar::class);
        $mienne = $registrar->registerForBooking($this->nuiteeTerminee($hote));
        $registrar->registerForBooking($this->nuiteeTerminee($autre));

        $this->travel(7)->days();
        $registrar->promoteEligibles();

        Sanctum::actingAs($hote);

        $reponse = $this->getJson('/api/v1/reversements/mine')->assertOk();

        $reponse->assertJsonCount(1, 'data');
        $reponse->assertJsonPath('data.0.reference', $mienne->reference);

        // ⚠️ Ce que Kaikun retient n'est pas montré au partenaire.
        $this->assertArrayNotHasKey('commission_xof', $reponse->json('data.0'));
        $this->assertArrayNotHasKey('beneficiary', $reponse->json('data.0'));
    }

    public function test_l_ecran_s_ouvre_sur_ce_qui_reste_du_pas_sur_l_archive(): void
    {
        $hote = $this->withRole(UserRole::PROPRIETAIRE->value);
        $registrar = app(PartnerDueRegistrar::class);

        $vivante = $registrar->registerForBooking($this->nuiteeTerminee($hote));
        $soldee = $registrar->registerForBooking($this->nuiteeTerminee($hote, 50_000));
        $soldee->update(['status' => PartnerDueStatus::PAYEE->value]);

        Sanctum::actingAs($hote);

        $this->getJson('/api/v1/reversements/mine')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', $vivante->reference);

        $this->getJson('/api/v1/reversements/mine?status=payee')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', $soldee->reference);
    }

    public function test_un_prestataire_voit_son_historique_de_versements_avec_justificatif(): void
    {
        Storage::fake('local');

        $hote = $this->withRole(UserRole::PROPRIETAIRE->value);
        $due = app(PartnerDueRegistrar::class)->registerForBooking($this->nuiteeTerminee($hote));

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));
        $payoutId = $this->postJson('/api/v1/admin/partner-payouts', ['due_ids' => [$due->id]])
            ->json('data.payout.id');
        $this->post("/api/v1/admin/partner-payouts/{$payoutId}/pay", [
            'method' => 'wave',
            'proof' => UploadedFile::fake()->image('recu.jpg'),
        ])->assertOk();

        Sanctum::actingAs($hote);

        $reponse = $this->getJson('/api/v1/reversements/mine/payouts')->assertOk();
        $reponse->assertJsonCount(1, 'data');
        $ligne = $reponse->json('data.0');

        $this->assertTrue($ligne['has_proof']);
        $this->assertArrayNotHasKey('proof_path', $ligne);
        $this->assertArrayNotHasKey('created_by', $ligne);

        // Le justificatif descend bien via l'URL signée self-service.
        $this->get($ligne['proof_url'])->assertOk();
    }

    public function test_un_autre_beneficiaire_ne_voit_pas_l_historique_d_un_tiers(): void
    {
        $hote = $this->withRole(UserRole::PROPRIETAIRE->value);
        $autre = $this->withRole(UserRole::PROPRIETAIRE->value);

        $due = app(PartnerDueRegistrar::class)->registerForBooking($this->nuiteeTerminee($hote));

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));
        $payoutId = $this->postJson('/api/v1/admin/partner-payouts', ['due_ids' => [$due->id]])
            ->json('data.payout.id');
        $this->post("/api/v1/admin/partner-payouts/{$payoutId}/pay", [
            'method' => 'wave',
            'proof' => UploadedFile::fake()->image('recu.jpg'),
        ])->assertOk();

        Sanctum::actingAs($autre);

        $this->getJson('/api/v1/reversements/mine/payouts')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_le_justificatif_self_service_n_est_accessible_que_par_url_signee(): void
    {
        Storage::fake('local');

        $hote = $this->withRole(UserRole::PROPRIETAIRE->value);
        $due = app(PartnerDueRegistrar::class)->registerForBooking($this->nuiteeTerminee($hote));

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));
        $payoutId = $this->postJson('/api/v1/admin/partner-payouts', ['due_ids' => [$due->id]])
            ->json('data.payout.id');
        $this->post("/api/v1/admin/partner-payouts/{$payoutId}/pay", [
            'method' => 'wave',
            'proof' => UploadedFile::fake()->image('recu.jpg'),
        ])->assertOk();

        $payout = PartnerPayout::query()->findOrFail($payoutId);

        $this->get("/api/v1/payouts/{$payout->id}/proof/mine")->assertStatus(403);
    }
}
