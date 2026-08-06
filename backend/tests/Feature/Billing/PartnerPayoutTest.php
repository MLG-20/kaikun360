<?php

namespace Tests\Feature\Billing;

use App\Enums\BookingStatus;
use App\Enums\PartnerDueStatus;
use App\Enums\PartnerPayoutStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\PartnerDue;
use App\Models\PartnerPayout;
use App\Models\Payment;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Immo\Models\Property;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Pro\Enums\MissionStatus;
use App\Modules\Pro\Models\Provider;
use App\Modules\Pro\Models\ProviderMission;
use App\Modules\Stay\Models\Stay;
use App\Support\Payouts\PartnerDueRegistrar;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SenegalGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests F8.16.a — LE REGISTRE DES REVERSEMENTS AUX PARTENAIRES.
 *
 * Écrits comme un **parcours d'argent** et non couche par couche : service rendu
 * → dette inscrite → délai écoulé → versement préparé → virement constaté avec
 * justificatif. C'est la leçon tirée de F8.15.a, où la couche « dépôt d'avis »
 * était verte alors que le produit ne pouvait pas produire l'état qu'elle
 * supposait.
 *
 * Le point de départ du chantier : Kaikun encaisse et commissionne sur tous les
 * univers depuis F8.4 mais ne reversait qu'en gestion locative. **Si un hôte
 * demandait ce qu'on lui devait, personne ne pouvait répondre.**
 */
class PartnerPayoutTest extends TestCase
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

    /** Une nuitée terminée, dont le bénéficiaire est le PROPRIÉTAIRE du bien. */
    private function nuiteeTerminee(User $proprietaire, int $montant = 200_000, int $commission = 24_000, int $joursDepuisFin = 30): Booking
    {
        $property = Property::factory()->published()->create(['owner_id' => $proprietaire->id]);
        $stay = Stay::factory()->create(['property_id' => $property->id]);

        return Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => Stay::class,
            'bookable_id' => $stay->id,
            'start_date' => now()->subDays($joursDepuisFin + 2)->toDateString(),
            'end_date' => now()->subDays($joursDepuisFin)->toDateString(),
            'amount_xof' => $montant,
            // ⚠️ La caution est volontairement NON NULLE dans tous ces tests :
            // c'est le piège que le registre doit éviter.
            'caution_xof' => 100_000,
            'commission_xof' => $commission,
            'status' => BookingStatus::TERMINEE->value,
        ]);
    }

    // --- 1. Qui est le bénéficiaire, et sur quelle assiette ------------------

    public function test_une_nuitee_terminee_doit_de_l_argent_au_proprietaire_du_bien_caution_exclue(): void
    {
        $proprietaire = $this->withRole(UserRole::PROPRIETAIRE->value);
        $booking = $this->nuiteeTerminee($proprietaire, montant: 200_000, commission: 24_000);

        app(PartnerDueRegistrar::class)->registerForBooking($booking);

        $due = PartnerDue::query()->firstOrFail();

        $this->assertSame($proprietaire->id, $due->beneficiary_id);
        // ⚠️ 200 000 et non 300 000 : la caution n'appartient PAS au partenaire,
        // elle est retenue puis restituée ou saisie. L'inclure reviendrait à lui
        // reverser l'argent du client.
        $this->assertSame(200_000, $due->gross_xof);
        $this->assertSame(24_000, $due->commission_xof);
        $this->assertSame(176_000, $due->net_xof);
    }

    public function test_un_circuit_doit_de_l_argent_a_son_prestataire(): void
    {
        $prestataire = $this->withRole(UserRole::PRESTATAIRE->value);
        // ⚠️ `TourismExperience.provider_id` référence `users` directement (et
        // non `providers`) : vérifié dans la migration. Toute la simplicité du
        // registre tient à ça — un seul type de bénéficiaire.
        $experience = TourismExperience::factory()->create(['provider_id' => $prestataire->id]);

        $booking = Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => TourismExperience::class,
            'bookable_id' => $experience->id,
            'start_date' => now()->subDays(30)->toDateString(),
            'amount_xof' => 60_000,
            'commission_xof' => 7_200,
            'status' => BookingStatus::TERMINEE->value,
        ]);

        app(PartnerDueRegistrar::class)->registerForBooking($booking);

        $this->assertDatabaseHas('partner_dues', [
            'beneficiary_id' => $prestataire->id,
            'net_xof' => 52_800,
        ]);
    }

    public function test_une_mission_terminee_doit_de_l_argent_au_prestataire_derriere_le_profil(): void
    {
        // ⚠️ Seule indirection du registre : `provider_missions.provider_id`
        // pointe sur `providers`, pas sur `users`. C'est par ici que passent le
        // team building et la construction, dont le devis « coûts + marge » ne
        // dit rien de ce qui revient à chaque intervenant.
        $utilisateur = $this->withRole(UserRole::PRESTATAIRE->value);
        $provider = Provider::factory()->create(['user_id' => $utilisateur->id]);

        $mission = ProviderMission::factory()->create([
            'provider_id' => $provider->id,
            'amount_xof' => 850_000,
            'commission_xof' => 102_000,
            'status' => MissionStatus::TERMINEE->value,
            'completed_at' => now()->subDays(30),
        ]);

        app(PartnerDueRegistrar::class)->registerForMission($mission);

        $this->assertDatabaseHas('partner_dues', [
            'beneficiary_id' => $utilisateur->id,
            'source_type' => ProviderMission::class,
            'net_xof' => 748_000,
        ]);
    }

    public function test_un_service_non_rendu_ne_doit_rien(): void
    {
        $proprietaire = $this->withRole(UserRole::PROPRIETAIRE->value);
        $booking = $this->nuiteeTerminee($proprietaire);
        $booking->update(['status' => BookingStatus::CONFIRMEE->value]);

        $this->assertNull(app(PartnerDueRegistrar::class)->registerForBooking($booking->fresh()));
        $this->assertDatabaseCount('partner_dues', 0);
    }

    // --- 2. Le délai de sûreté ----------------------------------------------

    public function test_une_dette_fraiche_attend_le_delai_avant_de_devenir_exigible(): void
    {
        $proprietaire = $this->withRole(UserRole::PROPRIETAIRE->value);
        // Séjour achevé hier : le délai de 7 jours court encore.
        $booking = $this->nuiteeTerminee($proprietaire, joursDepuisFin: 1);

        $registrar = app(PartnerDueRegistrar::class);
        $registrar->registerForBooking($booking);

        $due = PartnerDue::query()->firstOrFail();
        $this->assertSame(PartnerDueStatus::EN_ATTENTE, $due->status);

        // Le délai n'est pas écoulé : rien ne bascule.
        $this->assertSame(0, $registrar->promoteEligibles());

        // ⚠️ Le délai court sur la FIN DE SERVICE, pas sur l'instant du calcul :
        // un traitement lancé en retard ne doit pas retarder le partenaire.
        $this->travel(7)->days();
        $this->assertSame(1, $registrar->promoteEligibles());
        $this->assertSame(PartnerDueStatus::EXIGIBLE, $due->fresh()->status);
    }

    // --- 3. Idempotence : le risque de payer deux fois ----------------------

    public function test_rejouer_le_calcul_ne_cree_jamais_une_seconde_dette(): void
    {
        $proprietaire = $this->withRole(UserRole::PROPRIETAIRE->value);
        $this->nuiteeTerminee($proprietaire);

        $this->artisan('reversements:calculer')->assertSuccessful();
        $this->artisan('reversements:calculer')->assertSuccessful();
        $this->artisan('reversements:calculer')->assertSuccessful();

        // Sans l'unique en base sur (source_type, source_id), trois passages
        // auraient produit trois dettes — donc trois virements au partenaire.
        $this->assertDatabaseCount('partner_dues', 1);
    }

    public function test_le_calcul_ne_reanime_pas_une_dette_annulee(): void
    {
        $proprietaire = $this->withRole(UserRole::PROPRIETAIRE->value);
        $booking = $this->nuiteeTerminee($proprietaire);

        $registrar = app(PartnerDueRegistrar::class);
        $registrar->registerForBooking($booking);
        $registrar->cancelForSource($booking, 'Test');

        $this->artisan('reversements:calculer')->assertSuccessful();

        $this->assertSame(PartnerDueStatus::ANNULEE, PartnerDue::query()->firstOrFail()->status);
    }

    // --- 4. Le remboursement éteint la dette --------------------------------

    public function test_rembourser_le_client_eteint_la_dette_envers_le_partenaire(): void
    {
        Http::fake(['*' => Http::response(['success' => 1], 200)]);
        config()->set('services.paytech.base_url', 'https://paytech.sn/api');
        config()->set('services.paytech.api_key', 'test-key');
        config()->set('services.paytech.api_secret', 'test-secret');

        $proprietaire = $this->withRole(UserRole::PROPRIETAIRE->value);
        $booking = $this->nuiteeTerminee($proprietaire, montant: 200_000, commission: 24_000);
        app(PartnerDueRegistrar::class)->registerForBooking($booking);

        $payment = Payment::create([
            'reference' => 'PAY-'.uniqid(),
            'booking_id' => $booking->id,
            'amount_xof' => 200_000,
            'commission_xof' => 24_000,
            'provider_reference' => 'ptx_'.uniqid(),
            'status' => PaymentStatus::COMPLETE->value,
        ]);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->postJson("/api/v1/admin/payments/{$payment->id}/refund")->assertOk();

        // ⚠️ Sans cette extinction, le client est remboursé ET le partenaire
        // payé : Kaikun perd deux fois.
        $due = PartnerDue::query()->firstOrFail();
        $this->assertSame(PartnerDueStatus::ANNULEE, $due->status);
        $this->assertNotNull($due->cancelled_reason);
    }

    public function test_une_dette_deja_payee_n_est_pas_annulee_par_un_remboursement(): void
    {
        $proprietaire = $this->withRole(UserRole::PROPRIETAIRE->value);
        $booking = $this->nuiteeTerminee($proprietaire);
        $due = app(PartnerDueRegistrar::class)->registerForBooking($booking);
        $due->update(['status' => PartnerDueStatus::PAYEE->value]);

        // ⚠️ L'argent est parti : la marquer annulée ferait disparaître des
        // comptes un virement bien réel. L'écart devient une créance de Kaikun
        // sur le partenaire, à régler hors application.
        $this->assertFalse(app(PartnerDueRegistrar::class)->cancelForSource($booking, 'Remboursement'));
        $this->assertSame(PartnerDueStatus::PAYEE, $due->fresh()->status);
    }

    // --- 5. Le back-office ---------------------------------------------------

    public function test_un_agent_sans_gerer_paiements_n_approche_pas_les_reversements(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::AGENT_KAIKUN->value));

        // Reverser, c'est sortir de l'argent : même garde de GOUVERNANCE que le
        // remboursement, pas une permission opérationnelle.
        $this->getJson('/api/v1/admin/partner-dues')->assertStatus(403);
        $this->getJson('/api/v1/admin/partner-dues/beneficiaries')->assertStatus(403);
        $this->postJson('/api/v1/admin/partner-payouts', ['due_ids' => [1]])->assertStatus(403);
    }

    public function test_l_ecran_repond_enfin_a_la_question_qui_dois_je_payer_et_combien(): void
    {
        $hote = $this->withRole(UserRole::PROPRIETAIRE->value);
        $guide = $this->withRole(UserRole::PRESTATAIRE->value);

        // Deux séjours exigibles chez le même hôte, plus un tout frais.
        app(PartnerDueRegistrar::class)->registerForBooking($this->nuiteeTerminee($hote, 200_000, 24_000));
        app(PartnerDueRegistrar::class)->registerForBooking($this->nuiteeTerminee($hote, 100_000, 12_000));
        app(PartnerDueRegistrar::class)->registerForBooking($this->nuiteeTerminee($hote, 500_000, 0, joursDepuisFin: 1));

        $experience = TourismExperience::factory()->create(['provider_id' => $guide->id]);
        app(PartnerDueRegistrar::class)->registerForBooking(Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => TourismExperience::class,
            'bookable_id' => $experience->id,
            'start_date' => now()->subDays(30)->toDateString(),
            'amount_xof' => 60_000,
            'commission_xof' => 7_200,
            'status' => BookingStatus::TERMINEE->value,
        ]));

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $reponse = $this->getJson('/api/v1/admin/partner-dues/beneficiaries')->assertOk();

        // Une ligne par PARTENAIRE, pas par dette : on ne vire pas à une
        // réservation, on vire à quelqu'un.
        $reponse->assertJsonCount(2, 'data.beneficiaries');

        $ligneHote = collect($reponse->json('data.beneficiaries'))
            ->firstWhere('beneficiary.id', $hote->id);

        // 176 000 + 88 000 exigibles ; les 500 000 encore sous délai sont
        // comptés à part — les mélanger ferait virer de l'argent trop tôt.
        $this->assertSame(264_000, $ligneHote['payable_xof']);
        $this->assertSame(500_000, $ligneHote['pending_xof']);
        $this->assertSame(3, $ligneHote['dues_count']);

        $this->assertSame(316_800, $reponse->json('data.totals.payable_xof'));
    }

    public function test_le_parcours_complet_du_virement_avec_justificatif_obligatoire(): void
    {
        Storage::fake('local');

        $hote = $this->withRole(UserRole::PROPRIETAIRE->value);
        $due = app(PartnerDueRegistrar::class)->registerForBooking(
            $this->nuiteeTerminee($hote, 200_000, 24_000),
        );

        $admin = $this->withRole(UserRole::ADMIN->value);
        Sanctum::actingAs($admin);

        // 1) L'agent prépare le lot.
        $creation = $this->postJson('/api/v1/admin/partner-payouts', [
            'due_ids' => [$due->id],
        ])->assertCreated();

        $payoutId = $creation->json('data.payout.id');
        $this->assertSame(176_000, $creation->json('data.payout.amount_xof'));

        // ⚠️ La dette est rattachée MAIS pas encore payée : un lot préparé puis
        // abandonné ne doit pas laisser croire que l'argent est parti.
        $this->assertSame(PartnerDueStatus::EXIGIBLE, $due->fresh()->status);
        $this->assertSame($payoutId, $due->fresh()->payout_id);

        // 2) Sans justificatif, le constat est refusé.
        $this->postJson("/api/v1/admin/partner-payouts/{$payoutId}/pay", [
            'method' => 'wave',
        ])->assertStatus(422)->assertJsonValidationErrors('proof');

        // 3) Avec le justificatif, le virement est constaté.
        $this->post("/api/v1/admin/partner-payouts/{$payoutId}/pay", [
            'method' => 'wave',
            'external_reference' => 'TX-998877',
            'proof' => UploadedFile::fake()->image('recu-wave.jpg'),
        ])->assertOk();

        $payout = PartnerPayout::query()->findOrFail($payoutId);
        $this->assertSame(PartnerPayoutStatus::PAYE, $payout->status);
        $this->assertNotNull($payout->paid_at);
        $this->assertSame($admin->id, $payout->paid_by);
        // Le justificatif existe VRAIMENT sur le disque privé : `owner_payouts`
        // porte la même colonne depuis B4.4 sans que rien ne l'écrive jamais.
        $this->assertNotNull($payout->proof_path);
        Storage::disk('local')->assertExists($payout->proof_path);

        // 4) La dette est enfin soldée.
        $this->assertSame(PartnerDueStatus::PAYEE, $due->fresh()->status);
    }

    public function test_une_dette_ne_peut_pas_entrer_dans_deux_versements(): void
    {
        $hote = $this->withRole(UserRole::PROPRIETAIRE->value);
        $due = app(PartnerDueRegistrar::class)->registerForBooking($this->nuiteeTerminee($hote));

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->postJson('/api/v1/admin/partner-payouts', ['due_ids' => [$due->id]])->assertCreated();

        // Second lot sur la même dette : le partenaire serait payé deux fois.
        $this->postJson('/api/v1/admin/partner-payouts', ['due_ids' => [$due->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('due_ids');
    }

    public function test_un_versement_ne_melange_jamais_deux_beneficiaires(): void
    {
        $hote = $this->withRole(UserRole::PROPRIETAIRE->value);
        $autre = $this->withRole(UserRole::PROPRIETAIRE->value);

        $registrar = app(PartnerDueRegistrar::class);
        $a = $registrar->registerForBooking($this->nuiteeTerminee($hote));
        $b = $registrar->registerForBooking($this->nuiteeTerminee($autre));

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        // Un virement dont personne ne saurait à qui il a été fait, et un
        // justificatif impossible à rattacher.
        $this->postJson('/api/v1/admin/partner-payouts', ['due_ids' => [$a->id, $b->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('due_ids');
    }

    public function test_un_virement_echoue_rend_les_dettes_a_nouveau_payables(): void
    {
        $hote = $this->withRole(UserRole::PROPRIETAIRE->value);
        $due = app(PartnerDueRegistrar::class)->registerForBooking($this->nuiteeTerminee($hote));

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $payoutId = $this->postJson('/api/v1/admin/partner-payouts', ['due_ids' => [$due->id]])
            ->assertCreated()->json('data.payout.id');

        $this->postJson("/api/v1/admin/partner-payouts/{$payoutId}/fail", [
            'note' => 'Numéro Wave erroné, rejet de l\'opérateur.',
        ])->assertOk();

        // ⚠️ L'argent n'est pas parti : la créance du partenaire n'a pas disparu.
        $due->refresh();
        $this->assertSame(PartnerDueStatus::EXIGIBLE, $due->status);
        $this->assertNull($due->payout_id);

        // Et elle repart bien dans un nouveau lot.
        $this->postJson('/api/v1/admin/partner-payouts', ['due_ids' => [$due->id]])->assertCreated();
    }

    public function test_un_versement_deja_constate_ne_peut_plus_etre_declare_en_echec(): void
    {
        Storage::fake('local');

        $hote = $this->withRole(UserRole::PROPRIETAIRE->value);
        $due = app(PartnerDueRegistrar::class)->registerForBooking($this->nuiteeTerminee($hote));

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $payoutId = $this->postJson('/api/v1/admin/partner-payouts', ['due_ids' => [$due->id]])
            ->json('data.payout.id');

        $this->post("/api/v1/admin/partner-payouts/{$payoutId}/pay", [
            'method' => 'virement',
            'proof' => UploadedFile::fake()->create('avis.pdf', 40, 'application/pdf'),
        ])->assertOk();

        $this->postJson("/api/v1/admin/partner-payouts/{$payoutId}/fail", ['note' => 'Erreur'])
            ->assertStatus(422);
    }

    public function test_le_registre_s_ouvre_sur_ce_qui_reste_du_pas_sur_l_archive(): void
    {
        $hote = $this->withRole(UserRole::PROPRIETAIRE->value);
        $registrar = app(PartnerDueRegistrar::class);

        $vivante = $registrar->registerForBooking($this->nuiteeTerminee($hote));
        $soldee = $registrar->registerForBooking($this->nuiteeTerminee($hote, 50_000));
        $soldee->update(['status' => PartnerDueStatus::PAYEE->value]);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        // Ouvrir l'écran sur l'archive des dettes soldées ferait chercher le
        // travail à faire.
        $this->getJson('/api/v1/admin/partner-dues')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', $vivante->reference);

        // Le filtre explicite donne accès à l'archive.
        $this->getJson('/api/v1/admin/partner-dues?status=payee')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', $soldee->reference);
    }

    public function test_chaque_univers_se_nomme_lisiblement_dans_le_registre(): void
    {
        // ⚠️ Ce test existe parce qu'une première version cherchait
        // `isset($cible->title)` : vrai pour un circuit, FAUX pour un véhicule
        // (`brand`/`model`) et pour un trajet (`departure`/`destination`). Le
        // repli affichait « Vehicle » et « MobilityService » — le nom de la
        // classe — sur l'écran même où l'on décide d'un virement.
        $prestataire = $this->withRole(UserRole::PRESTATAIRE->value);

        $vehicule = Vehicle::factory()->create([
            'provider_id' => $prestataire->id,
            // Sans marque ni modèle : c'est le repli sur le TYPE qui est visé,
            // et ce type est un enum — le rendre tel quel lève une TypeError.
            'brand' => null,
            'model' => null,
        ]);

        $trajet = MobilityService::factory()->create([
            'provider_id' => $prestataire->id,
            'departure' => 'Dakar',
            'destination' => 'Mbour',
        ]);

        $registrar = app(PartnerDueRegistrar::class);

        foreach ([[Vehicle::class, $vehicule], [MobilityService::class, $trajet]] as [$type, $cible]) {
            $registrar->registerForBooking(Booking::create([
                'reference' => 'BK-'.uniqid(),
                'user_id' => User::factory()->create()->id,
                'bookable_type' => $type,
                'bookable_id' => $cible->id,
                'start_date' => now()->subDays(30)->toDateString(),
                'end_date' => now()->subDays(28)->toDateString(),
                'amount_xof' => 50_000,
                'commission_xof' => 6_000,
                'status' => BookingStatus::TERMINEE->value,
            ]));
        }

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $labels = collect($this->getJson('/api/v1/admin/partner-dues')->assertOk()->json('data'))
            ->pluck('source.label');

        $this->assertTrue($labels->contains('Dakar → Mbour'));
        // Le véhicule tombe sur le libellé de son type, jamais sur « Vehicle ».
        $this->assertFalse($labels->contains('Vehicle'));
        $this->assertFalse($labels->contains('MobilityService'));
        $this->assertFalse($labels->contains(null));
    }

    public function test_le_justificatif_n_est_accessible_que_par_url_signee(): void
    {
        Storage::fake('local');

        $hote = $this->withRole(UserRole::PROPRIETAIRE->value);
        $due = app(PartnerDueRegistrar::class)->registerForBooking($this->nuiteeTerminee($hote));

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $payoutId = $this->postJson('/api/v1/admin/partner-payouts', ['due_ids' => [$due->id]])
            ->json('data.payout.id');

        $reponse = $this->post("/api/v1/admin/partner-payouts/{$payoutId}/pay", [
            'method' => 'orange_money',
            'proof' => UploadedFile::fake()->image('recu.png'),
        ])->assertOk();

        // Le chemin de stockage n'est JAMAIS exposé : une preuve de virement
        // porte des coordonnées.
        $payout = $reponse->json('data.payout');
        $this->assertArrayNotHasKey('proof_path', $payout);
        $this->assertTrue($payout['has_proof']);

        // Sans signature valide, la route refuse.
        $this->get("/api/v1/admin/partner-payouts/{$payoutId}/proof")->assertStatus(403);

        // Avec l'URL signée servie par la ressource, le fichier descend.
        $this->get($payout['proof_url'])->assertOk();
    }
}
