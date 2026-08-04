<?php

namespace Tests\Feature\Build;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Build\Enums\ConstructionLot;
use App\Modules\Build\Enums\ConstructionQuoteStatus;
use App\Modules\Build\Enums\ConstructionRequestStatus;
use App\Modules\Build\Models\ConstructionQuote;
use App\Modules\Build\Models\ConstructionRequest;
use App\Modules\Build\Notifications\ConstructionQuoteAcceptedNotification;
use App\Modules\Build\Notifications\ConstructionQuoteSentNotification;
use App\Modules\Build\Services\ConstructionQuoteComposer;
use App\Modules\Core\Enums\UserRole;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests des devis de chantier (F7.3.e2).
 *
 * Fonction du CDC §6 *Construction* qui n'avait aucune implémentation : la
 * plateforme ne produisait qu'un coût estimé par le simulateur. Ces tests couvrent
 * la composition ventilée par lot, la marge (réglage back-office), le cycle
 * brouillon → envoyé → accepté/refusé, les statuts du dossier et le partage des
 * droits (chiffrer = `gerer:chantiers`, répondre = le client seul).
 */
class ConstructionQuoteTest extends TestCase
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
        $agent->givePermissionTo(AdminPermission::operational());

        return $agent;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lines(): array
    {
        return [
            // Saisi dans le désordre exprès : le composeur doit remettre les lots
            // dans l'ordre d'exécution du chantier.
            ['lot' => ConstructionLot::FINITIONS->value, 'label' => 'Peinture', 'unit' => 'm2', 'quantity' => 120, 'unit_price_xof' => 3_500],
            ['lot' => ConstructionLot::FONDATIONS->value, 'label' => 'Semelles', 'unit' => 'm3', 'quantity' => 18.5, 'unit_price_xof' => 90_000],
        ];
    }

    public function test_un_agent_compose_un_devis_ventile_et_ordonne(): void
    {
        $request = ConstructionRequest::factory()->create([
            'status' => ConstructionRequestStatus::SOUMISE->value,
        ]);

        Sanctum::actingAs($this->agent());

        $response = $this->postJson("/api/v1/construction-requests/{$request->id}/quotes", [
            'lines' => $this->lines(),
            'margin_rate' => 10,
        ]);

        // 120 × 3 500 = 420 000 ; 18,5 × 90 000 = 1 665 000 → 2 085 000.
        // Marge 10 % = 208 500 → total 2 293 500.
        $response->assertCreated()
            ->assertJsonPath('data.quote.status', ConstructionQuoteStatus::BROUILLON->value)
            ->assertJsonPath('data.quote.subtotal_xof', 2_085_000)
            ->assertJsonPath('data.quote.margin_xof', 208_500)
            ->assertJsonPath('data.quote.total_xof', 2_293_500)
            // Les fondations passent avant les finitions, quel que soit l'ordre de saisie.
            ->assertJsonPath('data.quote.lines.0.lot', ConstructionLot::FONDATIONS->value)
            ->assertJsonPath('data.quote.lines.1.lot', ConstructionLot::FINITIONS->value)
            // Le libellé du lot est figé dans la ligne : le devis est un document.
            ->assertJsonPath('data.quote.lines.0.lot_label', 'Fondations');

        // Le dossier passe « en étude » dès qu'un chiffrage existe.
        $this->assertSame(ConstructionRequestStatus::EN_ETUDE, $request->fresh()->status);
    }

    public function test_la_marge_par_defaut_vient_du_reglage_back_office(): void
    {
        Settings::set('build.margin_rate', 20.0);

        $request = ConstructionRequest::factory()->create();

        $quote = app(ConstructionQuoteComposer::class)->composeFor($request, [
            ['lot' => ConstructionLot::GROS_OEUVRE->value, 'quantity' => 1, 'unit_price_xof' => 1_000_000],
        ]);

        $this->assertSame(20.0, (float) $quote->margin_rate);
        $this->assertSame(200_000, $quote->margin_xof);
        $this->assertSame(1_200_000, $quote->total_xof);
    }

    public function test_un_devis_se_compose_puis_s_envoie_puis_s_accepte(): void
    {
        $client = User::factory()->create();
        $request = ConstructionRequest::factory()->create([
            'client_id' => $client->id,
            'status' => ConstructionRequestStatus::SOUMISE->value,
        ]);

        // 1. L'agent chiffre.
        Sanctum::actingAs($this->agent());
        $quoteId = $this->postJson("/api/v1/construction-requests/{$request->id}/quotes", [
            'lines' => $this->lines(),
        ])->json('data.quote.id');

        // 2. L'agent envoie.
        $this->patchJson("/api/v1/construction-quotes/{$quoteId}/send")
            ->assertOk()
            ->assertJsonPath('data.quote.status', ConstructionQuoteStatus::ENVOYE->value);
        $this->assertSame(ConstructionRequestStatus::DEVIS_ENVOYE, $request->fresh()->status);

        // 3. Le client accepte.
        Sanctum::actingAs($client);
        $this->patchJson("/api/v1/construction-quotes/{$quoteId}/accept")
            ->assertOk()
            ->assertJsonPath('data.quote.status', ConstructionQuoteStatus::ACCEPTE->value);

        $this->assertSame(ConstructionRequestStatus::ACCEPTEE, $request->fresh()->status);
    }

    public function test_un_refus_ne_fait_pas_regresser_le_dossier(): void
    {
        $client = User::factory()->create();
        $request = ConstructionRequest::factory()->create(['client_id' => $client->id]);

        Sanctum::actingAs($this->agent());
        $quoteId = $this->postJson("/api/v1/construction-requests/{$request->id}/quotes", [
            'lines' => $this->lines(),
        ])->json('data.quote.id');
        $this->patchJson("/api/v1/construction-quotes/{$quoteId}/send")->assertOk();

        Sanctum::actingAs($client);
        $this->patchJson("/api/v1/construction-quotes/{$quoteId}/refuse")
            ->assertOk()
            ->assertJsonPath('data.quote.status', ConstructionQuoteStatus::REFUSE->value);

        // Un refus appelle un devis révisé : le dossier reste au stade « devis envoyé ».
        $this->assertSame(ConstructionRequestStatus::DEVIS_ENVOYE, $request->fresh()->status);
    }

    public function test_un_devis_non_envoye_n_est_pas_acceptable(): void
    {
        $client = User::factory()->create();
        $request = ConstructionRequest::factory()->create(['client_id' => $client->id]);

        Sanctum::actingAs($this->agent());
        $quoteId = $this->postJson("/api/v1/construction-requests/{$request->id}/quotes", [
            'lines' => $this->lines(),
        ])->json('data.quote.id');

        Sanctum::actingAs($client);
        $this->patchJson("/api/v1/construction-quotes/{$quoteId}/accept")->assertStatus(422);
    }

    public function test_un_devis_deja_envoye_ne_se_renvoie_pas(): void
    {
        $request = ConstructionRequest::factory()->create();

        Sanctum::actingAs($this->agent());
        $quoteId = $this->postJson("/api/v1/construction-requests/{$request->id}/quotes", [
            'lines' => $this->lines(),
        ])->json('data.quote.id');

        $this->patchJson("/api/v1/construction-quotes/{$quoteId}/send")->assertOk();
        // Un second envoi écraserait en silence la réponse du client.
        $this->patchJson("/api/v1/construction-quotes/{$quoteId}/send")->assertStatus(422);
    }

    public function test_l_agent_ne_repond_pas_a_la_place_du_client(): void
    {
        $request = ConstructionRequest::factory()->create();

        $agent = $this->agent();
        Sanctum::actingAs($agent);
        $quoteId = $this->postJson("/api/v1/construction-requests/{$request->id}/quotes", [
            'lines' => $this->lines(),
        ])->json('data.quote.id');
        $this->patchJson("/api/v1/construction-quotes/{$quoteId}/send")->assertOk();

        // Accepter est un engagement financier : la policy `respond` ne l'autorise
        // qu'au client propriétaire du dossier.
        $this->patchJson("/api/v1/construction-quotes/{$quoteId}/accept")->assertForbidden();
    }

    public function test_un_client_ne_chiffre_pas_son_propre_devis(): void
    {
        $client = User::factory()->create();
        $request = ConstructionRequest::factory()->create(['client_id' => $client->id]);

        Sanctum::actingAs($client);

        $this->postJson("/api/v1/construction-requests/{$request->id}/quotes", [
            'lines' => $this->lines(),
        ])->assertForbidden();
    }

    public function test_le_client_relit_les_devis_qu_on_lui_a_envoyes_mais_pas_les_brouillons(): void
    {
        // ⚠️ Ce test encodait l'inverse jusqu'en F3.9 : il composait un devis et
        // vérifiait que le client le voyait AUSSITÔT. C'était le bug — un
        // chiffrage en cours de composition, aux montants provisoires, était
        // lisible par le client avant que l'équipe ne l'ait envoyé.
        $client = User::factory()->create();
        $request = ConstructionRequest::factory()->create(['client_id' => $client->id]);

        Sanctum::actingAs($this->agent());
        $quoteId = $this->postJson("/api/v1/construction-requests/{$request->id}/quotes", [
            'lines' => $this->lines(),
        ])->assertCreated()->json('data.quote.id');

        // Brouillon : invisible pour le client…
        Sanctum::actingAs($client);
        $this->getJson("/api/v1/construction-requests/{$request->id}/quotes")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // …mais visible pour l'équipe, qui est en train de le composer.
        Sanctum::actingAs($this->agent());
        $this->getJson("/api/v1/construction-requests/{$request->id}/quotes")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Une fois envoyé, le client le lit.
        $this->patchJson("/api/v1/construction-quotes/{$quoteId}/send")->assertOk();

        Sanctum::actingAs($client);
        $this->getJson("/api/v1/construction-requests/{$request->id}/quotes")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'envoye');
    }

    public function test_l_envoi_d_un_devis_previent_le_client(): void
    {
        // Sans cette notification, l'écran d'acceptation existe mais personne ne
        // sait qu'il faut y aller : le devis pack du team building prévenait
        // l'entreprise depuis B9.3, la construction avait été oubliée.
        Notification::fake();

        $client = User::factory()->create();
        $request = ConstructionRequest::factory()->create(['client_id' => $client->id]);

        Sanctum::actingAs($this->agent());
        $quoteId = $this->postJson("/api/v1/construction-requests/{$request->id}/quotes", [
            'lines' => $this->lines(),
        ])->assertCreated()->json('data.quote.id');

        // Composer ne notifie pas : le devis n'est pas encore un document du client.
        Notification::assertNothingSent();

        $this->patchJson("/api/v1/construction-quotes/{$quoteId}/send")->assertOk();

        Notification::assertSentTo($client, ConstructionQuoteSentNotification::class);
    }

    public function test_la_liste_des_chantiers_du_client_porte_ses_devis(): void
    {
        // L'écran client liste les chantiers ET leurs devis : sans cette clé, il
        // faudrait un appel HTTP par dossier depuis le navigateur.
        $client = User::factory()->create();
        $request = ConstructionRequest::factory()->create(['client_id' => $client->id]);

        Sanctum::actingAs($this->agent());
        $quoteId = $this->postJson("/api/v1/construction-requests/{$request->id}/quotes", [
            'lines' => $this->lines(),
        ])->assertCreated()->json('data.quote.id');

        // Tant qu'il est en brouillon, il ne descend pas jusqu'au client.
        Sanctum::actingAs($client);
        $this->getJson('/api/v1/construction-requests/mine')
            ->assertOk()
            ->assertJsonCount(0, 'data.0.quotes');

        Sanctum::actingAs($this->agent());
        $this->patchJson("/api/v1/construction-quotes/{$quoteId}/send")->assertOk();

        Sanctum::actingAs($client);
        $this->getJson('/api/v1/construction-requests/mine')
            ->assertOk()
            ->assertJsonCount(1, 'data.0.quotes')
            ->assertJsonPath('data.0.quotes.0.status', 'envoye')
            // Les totaux voyagent : l'écran affiche le montant sans second appel.
            ->assertJsonPath('data.0.quotes.0.id', $quoteId);
    }

    public function test_un_tiers_ne_voit_pas_les_devis(): void
    {
        $request = ConstructionRequest::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/construction-requests/{$request->id}/quotes")->assertForbidden();
    }

    public function test_un_devis_sans_ligne_est_refuse(): void
    {
        $request = ConstructionRequest::factory()->create();

        Sanctum::actingAs($this->agent());

        $this->postJson("/api/v1/construction-requests/{$request->id}/quotes", ['lines' => []])
            ->assertStatus(422);
    }

    public function test_un_devis_complementaire_ne_fait_pas_regresser_un_chantier_en_cours(): void
    {
        $request = ConstructionRequest::factory()->create([
            'status' => ConstructionRequestStatus::EN_CHANTIER->value,
        ]);

        Sanctum::actingAs($this->agent());

        $quoteId = $this->postJson("/api/v1/construction-requests/{$request->id}/quotes", [
            'lines' => $this->lines(),
        ])->assertCreated()->json('data.quote.id');

        $this->patchJson("/api/v1/construction-quotes/{$quoteId}/send")->assertOk();

        // Ni la composition ni l'envoi ne ramènent le dossier en arrière.
        $this->assertSame(ConstructionRequestStatus::EN_CHANTIER, $request->fresh()->status);
    }

    // --- F8.14 : l'acceptation d'un devis de chantier devient exigible --------
    //
    // MÊME TROU, TROISIÈME FAMILLE DE DEVIS. Après les devis génériques (F8.11)
    // et ceux du team building, `ConstructionQuoteController::accept()` ne
    // faisait lui aussi que changer deux colonnes `status` : le client validait
    // un chantier à plusieurs millions et rien ne devenait payable.

    public function test_accepter_un_devis_de_chantier_cree_une_reservation_payable(): void
    {
        $client = User::factory()->create();
        $request = ConstructionRequest::factory()->create([
            'client_id' => $client->id,
            'status' => ConstructionRequestStatus::SOUMISE->value,
        ]);

        Sanctum::actingAs($this->agent());
        $quoteId = $this->postJson("/api/v1/construction-requests/{$request->id}/quotes", [
            'lines' => $this->lines(),
        ])->json('data.quote.id');
        $this->patchJson("/api/v1/construction-quotes/{$quoteId}/send")->assertOk();

        Sanctum::actingAs($client);
        $response = $this->patchJson("/api/v1/construction-quotes/{$quoteId}/accept")->assertOk();

        $quote = ConstructionQuote::findOrFail($quoteId);
        $booking = Booking::query()
            ->where('bookable_type', ConstructionQuote::class)
            ->where('bookable_id', $quote->id)
            ->firstOrFail();

        $this->assertSame($client->id, $booking->user_id);
        $this->assertSame((int) $quote->total_xof, $booking->amount_xof);
        $this->assertSame(BookingStatus::EN_ATTENTE, $booking->status);
        // ⚠️ La commission est la MARGE du devis, pas un pourcentage ajouté
        // par-dessus : le total signé par le client la contient déjà.
        $this->assertSame((int) $quote->margin_xof, $booking->commission_xof);

        $response->assertJsonPath('data.booking.id', $booking->id)
            ->assertJsonPath('data.booking.type', 'construction')
            ->assertJsonPath('data.booking.payable', true);
    }

    /**
     * Le montant exigible doit rester visible APRÈS un rechargement : sans la
     * réservation jointe au devis, l'écran ne saurait proposer « Régler » qu'au
     * retour immédiat du clic.
     */
    public function test_le_devis_accepte_porte_sa_reservation_dans_la_liste_du_client(): void
    {
        $client = User::factory()->create();
        $request = ConstructionRequest::factory()->create(['client_id' => $client->id]);

        Sanctum::actingAs($this->agent());
        $quoteId = $this->postJson("/api/v1/construction-requests/{$request->id}/quotes", [
            'lines' => $this->lines(),
        ])->json('data.quote.id');
        $this->patchJson("/api/v1/construction-quotes/{$quoteId}/send")->assertOk();

        Sanctum::actingAs($client);
        $this->patchJson("/api/v1/construction-quotes/{$quoteId}/accept")->assertOk();

        $devis = $this->getJson('/api/v1/construction-requests/mine')
            ->assertOk()
            ->json('data.0.quotes.0');

        $this->assertNotNull($devis['booking'] ?? null, 'Le devis accepté doit porter sa réservation.');
        $this->assertFalse($devis['booking']['is_paid']);
        $this->assertSame($devis['total_xof'], $devis['booking']['remaining_xof']);
    }

    /** Un refus ne crée évidemment rien d'exigible. */
    public function test_refuser_un_devis_de_chantier_ne_cree_aucune_reservation(): void
    {
        $client = User::factory()->create();
        $request = ConstructionRequest::factory()->create(['client_id' => $client->id]);

        Sanctum::actingAs($this->agent());
        $quoteId = $this->postJson("/api/v1/construction-requests/{$request->id}/quotes", [
            'lines' => $this->lines(),
        ])->json('data.quote.id');
        $this->patchJson("/api/v1/construction-quotes/{$quoteId}/send")->assertOk();

        Sanctum::actingAs($client);
        $this->patchJson("/api/v1/construction-quotes/{$quoteId}/refuse")->assertOk();

        $this->assertSame(0, Booking::query()->where('bookable_type', ConstructionQuote::class)->count());
    }

    /** Le client est prévenu que son chantier attend un règlement. */
    public function test_le_client_est_prevenu_que_son_chantier_attend_un_reglement(): void
    {
        Notification::fake();

        $client = User::factory()->create();
        $request = ConstructionRequest::factory()->create(['client_id' => $client->id]);

        Sanctum::actingAs($this->agent());
        $quoteId = $this->postJson("/api/v1/construction-requests/{$request->id}/quotes", [
            'lines' => $this->lines(),
        ])->json('data.quote.id');
        $this->patchJson("/api/v1/construction-quotes/{$quoteId}/send")->assertOk();

        Sanctum::actingAs($client);
        $this->patchJson("/api/v1/construction-quotes/{$quoteId}/accept")->assertOk();

        Notification::assertSentTo($client, ConstructionQuoteAcceptedNotification::class);
    }
}
