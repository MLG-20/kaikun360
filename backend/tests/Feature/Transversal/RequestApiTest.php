<?php

namespace Tests\Feature\Transversal;

use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Notifications\NewRequestToHandleNotification;
use App\Notifications\RequestStatusChangedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests d'API des demandes génériques (B11.2) : création + notification agents,
 * suivi, et changement de statut soumis à la machine à états stricte.
 */
class RequestApiTest extends TestCase
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
        $agent->givePermissionTo(AdminPermission::operational()); // F7.1.b : agent pleinement outillé (droits désormais délégués, plus portés par le rôle)

        return $agent;
    }

    public function test_un_utilisateur_cree_une_demande_et_notifie_les_agents(): void
    {
        Notification::fake();
        $agent = $this->agent();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/requests', [
            'service_type' => 'immo',
            'message' => 'Je cherche un terrain à Saly.',
            'budget_xof' => 20_000_000,
        ])
            ->assertCreated()
            ->assertJsonPath('data.request.status', 'recu');

        Notification::assertSentTo($agent, NewRequestToHandleNotification::class);
    }

    public function test_my_ne_liste_que_mes_demandes(): void
    {
        $user = User::factory()->create();
        ServiceRequest::factory()->count(2)->create(['user_id' => $user->id]);
        ServiceRequest::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/requests/my')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_je_consulte_le_detail_de_ma_demande(): void
    {
        $user = User::factory()->create();
        $request = ServiceRequest::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/requests/{$request->id}")
            ->assertOk()
            ->assertJsonPath('data.request.id', $request->id)
            ->assertJsonPath('data.request.reference', $request->reference);
    }

    public function test_je_ne_peux_pas_consulter_la_demande_d_un_autre(): void
    {
        $request = ServiceRequest::factory()->create(); // appartient à un autre

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/requests/{$request->id}")
            ->assertStatus(403);
    }

    public function test_un_agent_fait_avancer_le_statut_et_notifie_le_demandeur(): void
    {
        Notification::fake();
        $client = User::factory()->create();
        $request = ServiceRequest::factory()->create(['user_id' => $client->id]); // recu

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/requests/{$request->id}/status", ['status' => 'verification'])
            ->assertOk()
            ->assertJsonPath('data.request.status', 'verification');

        Notification::assertSentTo($client, RequestStatusChangedNotification::class);
    }

    public function test_une_transition_invalide_est_rejetee(): void
    {
        $request = ServiceRequest::factory()->create(); // recu

        Sanctum::actingAs($this->agent());

        // recu → devis (saut d'étape) interdit.
        $this->patchJson("/api/v1/requests/{$request->id}/status", ['status' => 'devis'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('requests', ['id' => $request->id, 'status' => 'recu']);
    }

    public function test_un_retour_en_arriere_est_rejete(): void
    {
        $request = ServiceRequest::factory()->status(RequestStatus::DEVIS)->create();

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/requests/{$request->id}/status", ['status' => 'visite'])
            ->assertStatus(422);
    }

    public function test_un_non_agent_ne_peut_pas_changer_le_statut(): void
    {
        $request = ServiceRequest::factory()->create();

        Sanctum::actingAs(User::factory()->create()); // simple client

        $this->patchJson("/api/v1/requests/{$request->id}/status", ['status' => 'verification'])
            ->assertStatus(403);
    }
}
