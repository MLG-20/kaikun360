<?php

namespace Tests\Feature\Transversal;

use App\Enums\WaitlistEntryStatus;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Notifications\NewWaitlistEntryNotification;
use App\Notifications\WaitlistEntryProcessedNotification;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Liste d'attente avant ouverture officielle (2026-08-14) : dépôt public,
 * 5 catégories, chacune avec ses propres champs dans `details`.
 */
class WaitlistTest extends TestCase
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

    public function test_un_proprietaire_s_inscrit(): void
    {
        $response = $this->postJson('/api/v1/waitlist', [
            'name' => 'Awa Diop',
            'phone' => '+221771234567',
            'category' => 'proprietaire',
            'details' => ['type_bien' => 'villa', 'nb_biens' => 2],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.waitlist_entry.status', 'nouveau')
            ->assertJsonPath('data.waitlist_entry.category', 'proprietaire');

        $this->assertDatabaseHas('waitlist_entries', [
            'name' => 'Awa Diop',
            'category' => 'proprietaire',
            'status' => WaitlistEntryStatus::NOUVEAU->value,
        ]);
    }

    public function test_un_prestataire_s_inscrit(): void
    {
        $this->postJson('/api/v1/waitlist', [
            'name' => 'Moussa Ba',
            'phone' => '+221771112233',
            'category' => 'prestataire',
            'details' => ['type_service' => 'mobilite'],
        ])->assertCreated();

        $this->assertDatabaseHas('waitlist_entries', ['category' => 'prestataire']);
    }

    public function test_un_client_s_inscrit(): void
    {
        $this->postJson('/api/v1/waitlist', [
            'name' => 'Fatou Sy',
            'phone' => '+221774445566',
            'category' => 'client',
            'details' => ['univers' => 'sejours'],
        ])->assertCreated();

        $this->assertDatabaseHas('waitlist_entries', ['category' => 'client']);
    }

    public function test_une_entreprise_team_building_s_inscrit(): void
    {
        $this->postJson('/api/v1/waitlist', [
            'name' => 'Entreprise Démo',
            'phone' => '+221338001122',
            'category' => 'team_building',
            'details' => ['taille_equipe' => 25, 'budget_xof' => 3000000],
        ])->assertCreated();

        $this->assertDatabaseHas('waitlist_entries', ['category' => 'team_building']);
    }

    public function test_un_membre_de_la_diaspora_s_inscrit(): void
    {
        $this->postJson('/api/v1/waitlist', [
            'name' => 'Ibrahima Fall',
            'phone' => '+33612345678',
            'category' => 'diaspora',
            'details' => ['pays_residence' => 'France', 'type_projet' => 'construction'],
        ])->assertCreated();

        $this->assertDatabaseHas('waitlist_entries', ['category' => 'diaspora']);
    }

    public function test_le_champ_specifique_de_la_categorie_est_obligatoire(): void
    {
        $this->postJson('/api/v1/waitlist', [
            'name' => 'Awa Diop',
            'phone' => '+221771234567',
            'category' => 'proprietaire',
            // 'details.type_bien' manquant.
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['details.type_bien']);
    }

    public function test_une_categorie_inconnue_est_refusee(): void
    {
        $this->postJson('/api/v1/waitlist', [
            'name' => 'Awa Diop',
            'phone' => '+221771234567',
            'category' => 'inexistante',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }

    public function test_le_depot_est_public(): void
    {
        // Aucune session : le prospect n'a pas de compte.
        $this->postJson('/api/v1/waitlist', [
            'name' => 'Awa Diop',
            'phone' => '+221771234567',
            'category' => 'client',
            'details' => ['univers' => 'immobilier'],
        ])->assertCreated();
    }

    public function test_l_inscription_alerte_l_equipe(): void
    {
        NotificationFacade::fake();

        $agent = $this->agent();

        $this->postJson('/api/v1/waitlist', [
            'name' => 'Awa Diop',
            'phone' => '+221771234567',
            'category' => 'client',
            'details' => ['univers' => 'immobilier'],
        ])->assertCreated();

        NotificationFacade::assertSentTo($agent, NewWaitlistEntryNotification::class);
    }

    public function test_l_alerte_ne_part_qu_a_l_equipe(): void
    {
        NotificationFacade::fake();

        $this->agent();
        $simple = User::factory()->create();

        $this->postJson('/api/v1/waitlist', [
            'name' => 'Awa Diop',
            'phone' => '+221771234567',
            'category' => 'client',
            'details' => ['univers' => 'immobilier'],
        ])->assertCreated();

        NotificationFacade::assertNotSentTo($simple, NewWaitlistEntryNotification::class);
    }

    // =========================================================================
    // Écran back-office de consultation (2026-08-14) — patron ContactController.

    public function test_un_anonyme_ne_peut_pas_lister_la_liste_d_attente(): void
    {
        $this->getJson('/api/v1/admin/waitlist')->assertUnauthorized();
    }

    public function test_un_utilisateur_sans_permission_ne_peut_pas_lister(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/waitlist')->assertForbidden();
    }

    public function test_un_agent_liste_les_inscriptions(): void
    {
        WaitlistEntry::create([
            'name' => 'Awa Diop', 'phone' => '+221771234567',
            'category' => 'proprietaire', 'details' => ['type_bien' => 'villa', 'nb_biens' => 2],
            'status' => WaitlistEntryStatus::NOUVEAU->value,
        ]);

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/waitlist')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.pending', 1);
    }

    public function test_la_liste_se_filtre_par_categorie_et_statut(): void
    {
        WaitlistEntry::create([
            'name' => 'Awa Diop', 'phone' => '+221771234567',
            'category' => 'proprietaire', 'details' => ['type_bien' => 'villa', 'nb_biens' => 2],
            'status' => WaitlistEntryStatus::NOUVEAU->value,
        ]);
        WaitlistEntry::create([
            'name' => 'Moussa Ba', 'phone' => '+221771112233',
            'category' => 'prestataire', 'details' => ['type_service' => 'mobilite'],
            'status' => WaitlistEntryStatus::TRAITE->value,
        ]);

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/waitlist?category=proprietaire')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/admin/waitlist?status=traite')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', 'prestataire');
    }

    public function test_un_agent_ouvre_la_fiche_d_une_inscription(): void
    {
        $entry = WaitlistEntry::create([
            'name' => 'Awa Diop', 'phone' => '+221771234567',
            'category' => 'proprietaire', 'details' => ['type_bien' => 'villa', 'nb_biens' => 2],
            'status' => WaitlistEntryStatus::NOUVEAU->value,
        ]);

        Sanctum::actingAs($this->agent());

        $this->getJson("/api/v1/admin/waitlist/{$entry->id}")
            ->assertOk()
            ->assertJsonPath('data.waitlist_entry.id', $entry->id)
            ->assertJsonPath('data.waitlist_entry.name', 'Awa Diop');
    }

    public function test_la_fiche_est_fermee_a_qui_n_a_pas_la_permission(): void
    {
        $entry = WaitlistEntry::create([
            'name' => 'Awa Diop', 'phone' => '+221771234567',
            'category' => 'proprietaire', 'details' => ['type_bien' => 'villa', 'nb_biens' => 2],
            'status' => WaitlistEntryStatus::NOUVEAU->value,
        ]);

        $this->getJson("/api/v1/admin/waitlist/{$entry->id}")->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/admin/waitlist/{$entry->id}")->assertForbidden();
    }

    public function test_un_agent_marque_une_inscription_comme_traitee(): void
    {
        $entry = WaitlistEntry::create([
            'name' => 'Awa Diop', 'phone' => '+221771234567',
            'category' => 'proprietaire', 'details' => ['type_bien' => 'villa', 'nb_biens' => 2],
            'status' => WaitlistEntryStatus::NOUVEAU->value,
        ]);

        $agent = $this->agent();
        Sanctum::actingAs($agent);

        $this->patchJson("/api/v1/admin/waitlist/{$entry->id}", [
            'status' => WaitlistEntryStatus::TRAITE->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.waitlist_entry.status', 'traite')
            ->assertJsonPath('data.waitlist_entry.handled_by', $agent->name);

        $this->assertDatabaseHas('waitlist_entries', [
            'id' => $entry->id,
            'status' => WaitlistEntryStatus::TRAITE->value,
            'handled_by' => $agent->id,
        ]);
        $this->assertNotNull($entry->fresh()->handled_at);
    }

    public function test_le_retour_a_nouveau_efface_l_agent_et_l_horodatage(): void
    {
        $entry = WaitlistEntry::create([
            'name' => 'Awa Diop', 'phone' => '+221771234567',
            'category' => 'proprietaire', 'details' => ['type_bien' => 'villa', 'nb_biens' => 2],
            'status' => WaitlistEntryStatus::NOUVEAU->value,
        ]);

        $agent = $this->agent();
        Sanctum::actingAs($agent);

        $this->patchJson("/api/v1/admin/waitlist/{$entry->id}", ['status' => 'traite'])->assertOk();
        $this->patchJson("/api/v1/admin/waitlist/{$entry->id}", ['status' => 'nouveau'])->assertOk();

        $entry->refresh();
        $this->assertNull($entry->handled_by);
        $this->assertNull($entry->handled_at);
    }

    public function test_les_referentiels_de_filtrage_sont_exposes(): void
    {
        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/waitlist/filters')
            ->assertOk()
            ->assertJsonCount(5, 'data.categories')
            ->assertJsonCount(2, 'data.statuses');
    }

    // =========================================================================
    // Invitation du prospect au passage à « traité » (2026-08-14).

    public function test_le_passage_a_traite_invite_le_prospect_par_e_mail(): void
    {
        NotificationFacade::fake();

        $entry = WaitlistEntry::create([
            'name' => 'Awa Diop', 'phone' => '+221771234567', 'email' => 'awa@example.com',
            'category' => 'proprietaire', 'details' => ['type_bien' => 'villa', 'nb_biens' => 2],
            'status' => WaitlistEntryStatus::NOUVEAU->value,
        ]);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/waitlist/{$entry->id}", [
            'status' => WaitlistEntryStatus::TRAITE->value,
        ])->assertOk();

        NotificationFacade::assertSentOnDemand(
            WaitlistEntryProcessedNotification::class,
            fn ($notification) => $notification->entry->id === $entry->id,
        );
    }

    public function test_aucun_e_mail_si_le_prospect_n_a_pas_laisse_d_adresse(): void
    {
        NotificationFacade::fake();

        $entry = WaitlistEntry::create([
            'name' => 'Moussa Ba', 'phone' => '+221771112233',
            'category' => 'prestataire', 'details' => ['type_service' => 'mobilite'],
            'status' => WaitlistEntryStatus::NOUVEAU->value,
        ]);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/waitlist/{$entry->id}", [
            'status' => WaitlistEntryStatus::TRAITE->value,
        ])->assertOk();

        NotificationFacade::assertNothingSent();
    }

    public function test_repasser_a_nouveau_n_envoie_aucun_e_mail(): void
    {
        NotificationFacade::fake();

        $entry = WaitlistEntry::create([
            'name' => 'Awa Diop', 'phone' => '+221771234567', 'email' => 'awa@example.com',
            'category' => 'proprietaire', 'details' => ['type_bien' => 'villa', 'nb_biens' => 2],
            'status' => WaitlistEntryStatus::TRAITE->value,
            'handled_by' => $this->agent()->id,
            'handled_at' => now(),
        ]);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/waitlist/{$entry->id}", [
            'status' => WaitlistEntryStatus::NOUVEAU->value,
        ])->assertOk();

        NotificationFacade::assertNothingSent();
    }

    public function test_confirmer_un_statut_deja_traite_ne_renvoie_pas_l_e_mail(): void
    {
        NotificationFacade::fake();

        $entry = WaitlistEntry::create([
            'name' => 'Awa Diop', 'phone' => '+221771234567', 'email' => 'awa@example.com',
            'category' => 'proprietaire', 'details' => ['type_bien' => 'villa', 'nb_biens' => 2],
            'status' => WaitlistEntryStatus::TRAITE->value,
            'handled_by' => $this->agent()->id,
            'handled_at' => now(),
        ]);

        Sanctum::actingAs($this->agent());

        // Le dossier est déjà traité : ce PATCH ne fait que confirmer le même
        // statut (ex. un second agent qui clique sans recharger la page).
        $this->patchJson("/api/v1/admin/waitlist/{$entry->id}", [
            'status' => WaitlistEntryStatus::TRAITE->value,
        ])->assertOk();

        NotificationFacade::assertNothingSent();
    }
}
