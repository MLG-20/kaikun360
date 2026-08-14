<?php

namespace Tests\Feature\Transversal;

use App\Enums\WaitlistEntryStatus;
use App\Models\User;
use App\Notifications\NewWaitlistEntryNotification;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
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
}
