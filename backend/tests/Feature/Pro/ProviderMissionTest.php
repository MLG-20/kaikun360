<?php

namespace Tests\Feature\Pro;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Pro\Models\Provider;
use App\Modules\Pro\Models\ProviderMission;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests des missions prestataires (phase B10.3) : affectation (admin, prestataire
 * validé), commission figée, et transitions de statut par le prestataire.
 */
class ProviderMissionTest extends TestCase
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

    public function test_un_admin_affecte_une_mission_avec_commission(): void
    {
        $provider = Provider::factory()->validated()->create();

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/providers/{$provider->id}/missions", [
            'title' => 'Animation gala',
            'amount_xof' => 500_000,
        ])
            ->assertCreated()
            ->assertJsonPath('data.mission.status', 'affectee')
            // 12 % de 500 000 = 60 000.
            ->assertJsonPath('data.mission.commission_xof', 60_000);
    }

    public function test_on_ne_peut_pas_affecter_a_un_prestataire_non_valide(): void
    {
        $provider = Provider::factory()->create(); // en attente

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/providers/{$provider->id}/missions", [
            'title' => 'X', 'amount_xof' => 100_000,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('provider');
    }

    public function test_un_non_admin_ne_peut_pas_affecter(): void
    {
        $provider = Provider::factory()->validated()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/providers/{$provider->id}/missions", [
            'title' => 'X', 'amount_xof' => 100_000,
        ])->assertStatus(403);
    }

    public function test_le_prestataire_liste_ses_missions(): void
    {
        $user = User::factory()->create();
        $provider = Provider::factory()->validated()->create(['user_id' => $user->id]);
        ProviderMission::factory()->count(2)->create(['provider_id' => $provider->id]);
        ProviderMission::factory()->create(); // autre prestataire

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/provider-missions/mine')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_le_prestataire_fait_progresser_sa_mission(): void
    {
        $user = User::factory()->create();
        $provider = Provider::factory()->validated()->create(['user_id' => $user->id]);
        $mission = ProviderMission::factory()->create(['provider_id' => $provider->id]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/provider-missions/{$mission->id}/accept")
            ->assertOk()->assertJsonPath('data.mission.status', 'acceptee');
        $this->patchJson("/api/v1/provider-missions/{$mission->id}/start")
            ->assertOk()->assertJsonPath('data.mission.status', 'en_cours');
        $this->patchJson("/api/v1/provider-missions/{$mission->id}/complete")
            ->assertOk()->assertJsonPath('data.mission.status', 'terminee');
    }

    public function test_une_transition_invalide_est_refusee(): void
    {
        $user = User::factory()->create();
        $provider = Provider::factory()->validated()->create(['user_id' => $user->id]);
        $mission = ProviderMission::factory()->create(['provider_id' => $provider->id]); // affectee

        Sanctum::actingAs($user);

        // On ne peut pas « terminer » une mission qui n'a pas commencé.
        $this->patchJson("/api/v1/provider-missions/{$mission->id}/complete")
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_un_autre_prestataire_ne_touche_pas_la_mission(): void
    {
        $mission = ProviderMission::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/v1/provider-missions/{$mission->id}/accept")->assertStatus(403);
    }
}
