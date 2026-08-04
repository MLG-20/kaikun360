<?php

namespace Tests\Feature\TeamBuilding;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Modules\Core\Enums\ProfileType;
use App\Modules\Core\Enums\UserRole;
use App\Modules\TeamBuilding\Models\TeamBuildingQuote;
use App\Modules\TeamBuilding\Models\TeamBuildingRequest;
use App\Modules\TeamBuilding\Notifications\NewTeamBuildingRequestNotification;
use App\Modules\TeamBuilding\Notifications\TeamBuildingQuoteAcceptedNotification;
use App\Modules\TeamBuilding\Notifications\TeamBuildingQuoteSentNotification;
use App\Services\QuoteConversionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests d'API du module Team Building (phase B9.3) : dépôt + file admin,
 * composition/envoi/acceptation de devis, events et isolation par entreprise.
 */
class TeamBuildingApiTest extends TestCase
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

    private function components(): array
    {
        return [
            ['category' => 'hebergement', 'label' => 'Lodge', 'quantity' => 20, 'unit_price_xof' => 40_000],
            ['category' => 'activite', 'label' => 'Excursion', 'quantity' => 20, 'unit_price_xof' => 10_000],
        ];
    }

    public function test_une_entreprise_depose_une_demande_et_alimente_la_file_admin(): void
    {
        Notification::fake();
        $admin = $this->admin();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/team-building-requests', [
            'participants' => 30,
            'city' => 'Saly',
            'start_date' => now()->addMonth()->toDateString(),
            'end_date' => now()->addMonth()->addDays(2)->toDateString(),
            'needs' => ['hebergement' => true, 'activite' => true],
        ])
            ->assertCreated()
            ->assertJsonPath('data.request.status', 'nouveau');

        Notification::assertSentTo($admin, NewTeamBuildingRequestNotification::class);
    }

    public function test_mine_ne_liste_que_mes_demandes(): void
    {
        $company = User::factory()->create();
        TeamBuildingRequest::factory()->count(2)->create(['company_id' => $company->id]);
        TeamBuildingRequest::factory()->create();

        Sanctum::actingAs($company);

        $this->getJson('/api/v1/team-building-requests/mine')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_une_entreprise_ne_voit_pas_la_demande_d_une_autre(): void
    {
        $request = TeamBuildingRequest::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/team-building-requests/{$request->id}")->assertStatus(403);
    }

    public function test_la_file_back_office_est_refusee_a_une_entreprise(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/team-building-requests')->assertStatus(403);
    }

    public function test_un_admin_compose_un_devis_et_passe_la_demande_en_etude(): void
    {
        $request = TeamBuildingRequest::factory()->create();

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/team-building-requests/{$request->id}/quotes", [
            'components' => $this->components(),
        ])
            ->assertCreated()
            // 800 000 + 200 000 = 1 000 000 ; marge 15 % = 150 000 ; total 1 150 000.
            ->assertJsonPath('data.quote.total_xof', 1_150_000)
            ->assertJsonPath('data.quote.status', 'brouillon');

        $this->assertDatabaseHas('team_building_requests', [
            'id' => $request->id,
            'status' => 'en_etude',
        ]);
    }

    public function test_une_entreprise_ne_peut_pas_composer_de_devis(): void
    {
        $request = TeamBuildingRequest::factory()->create();

        Sanctum::actingAs(User::find($request->company_id));

        $this->postJson("/api/v1/team-building-requests/{$request->id}/quotes", [
            'components' => $this->components(),
        ])->assertStatus(403);
    }

    public function test_l_envoi_d_un_devis_notifie_l_entreprise(): void
    {
        Notification::fake();
        $company = User::factory()->create();
        $request = TeamBuildingRequest::factory()->create(['company_id' => $company->id]);
        $quote = TeamBuildingQuote::factory()->create(['request_id' => $request->id]);

        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/v1/team-building-quotes/{$quote->id}/send")
            ->assertOk()
            ->assertJsonPath('data.quote.status', 'envoye');

        $this->assertDatabaseHas('team_building_requests', ['id' => $request->id, 'status' => 'devis_envoye']);
        Notification::assertSentTo($company, TeamBuildingQuoteSentNotification::class);
    }

    public function test_l_envoi_d_un_devis_alimente_la_cloche_in_app_de_l_entreprise(): void
    {
        // Sans Notification::fake : on vérifie que le canal `database` écrit bien
        // une notification exploitable par la cloche de l'espace entreprise (F6).
        $company = User::factory()->create();
        $request = TeamBuildingRequest::factory()->create(['company_id' => $company->id]);
        $quote = TeamBuildingQuote::factory()->create(['request_id' => $request->id]);

        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/v1/team-building-quotes/{$quote->id}/send")->assertOk();

        $notification = $company->fresh()->notifications()->first();
        $this->assertNotNull($notification, 'Une notification in-app doit être créée pour l\'entreprise.');
        $this->assertSame('team_building', $notification->data['category']);
        $this->assertSame('/espace-entreprise/demandes/'.$request->id, $notification->data['action_url']);
    }

    public function test_l_entreprise_accepte_un_devis_envoye(): void
    {
        $company = User::factory()->create();
        $request = TeamBuildingRequest::factory()->create(['company_id' => $company->id]);
        $quote = TeamBuildingQuote::factory()->sent()->create(['request_id' => $request->id]);

        Sanctum::actingAs($company);

        $this->patchJson("/api/v1/team-building-quotes/{$quote->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.quote.status', 'accepte');

        $this->assertDatabaseHas('team_building_requests', ['id' => $request->id, 'status' => 'accepte']);
    }

    public function test_un_devis_non_envoye_ne_peut_pas_etre_accepte(): void
    {
        $company = User::factory()->create();
        $request = TeamBuildingRequest::factory()->create(['company_id' => $company->id]);
        $quote = TeamBuildingQuote::factory()->create(['request_id' => $request->id]); // brouillon

        Sanctum::actingAs($company);

        $this->patchJson("/api/v1/team-building-quotes/{$quote->id}/accept")
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_un_tiers_ne_peut_pas_accepter_le_devis(): void
    {
        $request = TeamBuildingRequest::factory()->create();
        $quote = TeamBuildingQuote::factory()->sent()->create(['request_id' => $request->id]);

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/v1/team-building-quotes/{$quote->id}/accept")->assertStatus(403);
    }

    // --- F8.14 : l'acceptation devient exigible --------------------------------
    //
    // LE TROU COMBLÉ ICI. `accept()` ne faisait que changer deux colonnes
    // `status`, et son écouteur se contentait d'écrire une ligne d'audit en
    // annonçant que « l'orchestration s'appuiera sur la couche Bookings/Quotes ».
    // Aucune réservation, donc aucun montant exigible — `POST /payments/initiate`
    // réclamant un `booking_id`, l'entreprise ne pouvait pas payer ce qu'elle
    // venait d'accepter. C'est le trou que F8.11 avait bouché sur les devis
    // génériques ; ce module, qui a les SIENS, avait été oublié.

    public function test_accepter_un_devis_team_building_cree_une_reservation_payable(): void
    {
        $company = User::factory()->create();
        $request = TeamBuildingRequest::factory()->create([
            'company_id' => $company->id,
            'participants' => 24,
        ]);
        $quote = TeamBuildingQuote::factory()->sent()->create(['request_id' => $request->id]);

        Sanctum::actingAs($company);

        $response = $this->patchJson("/api/v1/team-building-quotes/{$quote->id}/accept")->assertOk();

        $booking = Booking::query()
            ->where('bookable_type', TeamBuildingQuote::class)
            ->where('bookable_id', $quote->id)
            ->firstOrFail();

        // La réservation appartient à l'ENTREPRISE, porte le total du devis et
        // reste en attente : c'est l'encaissement qui la confirmera.
        $this->assertSame($company->id, $booking->user_id);
        $this->assertSame((int) $quote->total_xof, $booking->amount_xof);
        $this->assertSame(BookingStatus::EN_ATTENTE, $booking->status);
        // Un séminaire a des dates et un effectif : la réservation les porte, ce
        // qui la rend lisible sans rouvrir le devis.
        $this->assertSame(24, $booking->guests);

        // ⚠️ La commission est la MARGE DÉJÀ CHIFFRÉE dans le devis, pas un
        // pourcentage appliqué par-dessus : le total présenté à l'entreprise la
        // contient déjà, la recalculer facturerait deux fois la même rémunération.
        $this->assertSame((int) $quote->margin_xof, $booking->commission_xof);

        // La réponse porte la réservation : c'est elle qui donne à l'écran
        // l'identifiant attendu par la page de règlement.
        $response->assertJsonPath('data.booking.id', $booking->id)
            ->assertJsonPath('data.booking.payable', true)
            ->assertJsonPath('data.booking.type', 'team_building')
            ->assertJsonPath('data.booking.remaining_xof', (int) $quote->total_xof);
    }

    /**
     * IDEMPOTENCE. Le garde-fou du contrôleur (« seul un devis envoyé peut être
     * accepté ») bloque déjà la seconde acceptation, mais le service doit tenir
     * seul : un double clic ne doit jamais produire deux montants à payer pour un
     * seul séminaire.
     */
    public function test_la_conversion_ne_cree_jamais_deux_reservations(): void
    {
        $quote = TeamBuildingQuote::factory()->sent()->create();

        $premiere = app(QuoteConversionService::class)->convertTeamBuilding($quote);
        $seconde = app(QuoteConversionService::class)->convertTeamBuilding($quote);

        $this->assertSame($premiere->id, $seconde->id);
        $this->assertSame(1, Booking::query()->where('bookable_type', TeamBuildingQuote::class)->count());
    }

    /**
     * Le bout du circuit, et la raison d'être de la tranche : l'entreprise règle
     * son séminaire comme un client règle sa nuitée. Mode manuel (Wave/OM) — il
     * n'appelle pas PayTech, donc le test reste hermétique tout en empruntant le
     * vrai chemin d'initiation.
     */
    public function test_l_entreprise_peut_regler_la_reservation_issue_de_son_devis(): void
    {
        $company = User::factory()->create();
        $request = TeamBuildingRequest::factory()->create(['company_id' => $company->id]);
        $quote = TeamBuildingQuote::factory()->sent()->create(['request_id' => $request->id]);

        Sanctum::actingAs($company);

        $bookingId = $this->patchJson("/api/v1/team-building-quotes/{$quote->id}/accept")
            ->assertOk()
            ->json('data.booking.id');

        $this->postJson('/api/v1/payments/initiate', [
            'booking_id' => $bookingId,
            'mode' => 'manuel',
        ])->assertCreated();
    }

    /**
     * Et elle retrouve ce séminaire dans SES réservations, désigné par son
     * événement — « Séminaire — Saly, 24 participants » — et non par une
     * référence de devis qui ne dirait rien.
     */
    public function test_le_seminaire_apparait_dans_les_reservations_de_l_entreprise(): void
    {
        $company = User::factory()->create();
        $request = TeamBuildingRequest::factory()->create([
            'company_id' => $company->id,
            'city' => 'Saly',
            'participants' => 24,
        ]);
        $quote = TeamBuildingQuote::factory()->sent()->create(['request_id' => $request->id]);

        Sanctum::actingAs($company);
        $this->patchJson("/api/v1/team-building-quotes/{$quote->id}/accept")->assertOk();

        $ligne = $this->getJson('/api/v1/bookings/my')->assertOk()->json('data.0');

        $this->assertSame('team_building', $ligne['type']);
        $this->assertSame('Team building', $ligne['type_label']);
        $this->assertSame('Séminaire — Saly, 24 participants', $ligne['item_label']);
    }

    /**
     * Un accord suivi d'un silence est la meilleure façon de perdre un séminaire
     * déjà vendu : l'entreprise est prévenue, et le lien pointe vers SON espace
     * (leçon de F8.8 — le site a quatre espaces connectés, et `/mon-espace` est
     * fermé à un compte entreprise).
     */
    public function test_l_entreprise_est_prevenue_avec_un_lien_vers_son_propre_espace(): void
    {
        // Compte entreprise complet (profil + rôle), comme le produit
        // l'inscription : c'est le profil qui donne son espace au destinataire.
        $company = User::factory()->create();
        $company->profile()->create([
            'type' => ProfileType::ENTREPRISE->value,
            'verification_status' => 'non_verifie',
        ]);
        $company->assignRole(UserRole::ENTREPRISE->value);
        $request = TeamBuildingRequest::factory()->create(['company_id' => $company->id]);
        $quote = TeamBuildingQuote::factory()->sent()->create(['request_id' => $request->id]);

        Sanctum::actingAs($company);
        $bookingId = $this->patchJson("/api/v1/team-building-quotes/{$quote->id}/accept")
            ->assertOk()
            ->json('data.booking.id');

        $notification = $company->fresh()->notifications()
            ->where('type', TeamBuildingQuoteAcceptedNotification::class)
            ->first();

        $this->assertNotNull($notification, "L'entreprise doit être prévenue que son devis est accepté.");
        $this->assertSame(
            '/espace-entreprise/reservations/'.$bookingId.'/paiement',
            $notification->data['action_url'],
        );
    }
}
