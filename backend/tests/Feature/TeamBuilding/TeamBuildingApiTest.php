<?php

namespace Tests\Feature\TeamBuilding;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\TeamBuilding\Models\TeamBuildingQuote;
use App\Modules\TeamBuilding\Models\TeamBuildingRequest;
use App\Modules\TeamBuilding\Notifications\NewTeamBuildingRequestNotification;
use App\Modules\TeamBuilding\Notifications\TeamBuildingQuoteSentNotification;
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
}
