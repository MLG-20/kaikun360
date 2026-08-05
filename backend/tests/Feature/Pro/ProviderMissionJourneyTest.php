<?php

namespace Tests\Feature\Pro;

use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Pro\Models\Provider;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * F8.15.d — le PARCOURS « on confie une mission, elle se termine, le client
 * note le prestataire ».
 *
 * POURQUOI CE FICHIER À CÔTÉ DE `ProviderMissionTest`
 * ---------------------------------------------------
 * `ProviderMissionTest` couvre la couche : affectation, transitions, revenus.
 * Elle passait au vert alors que **`POST /providers/{id}/missions` n'avait aucun
 * appelant** — aucune mission n'était créable depuis un écran, toutes venaient du
 * seeder. Conséquences invisibles depuis cette couche :
 *   - la **commission** de la marketplace Pro ne se déclenchait jamais en vrai ;
 *   - « Mes missions » / « Mes revenus » côté prestataire ne montraient que des
 *     données de démonstration ;
 *   - et surtout, **aucun prestataire ne pouvait être noté** : `ReviewPolicy`
 *     exige une **mission terminée** comme preuve de consommation. C'est la
 *     moitié de F8.15.a qui était restée inatteignable.
 *
 * On vérifie donc ici la chaîne entière, plus le fait que la fiche back-office
 * montre bien les missions (sans quoi on affecterait à l'aveugle).
 */
class ProviderMissionJourneyTest extends TestCase
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

    public function test_de_la_mission_confiee_a_l_avis_sur_le_prestataire(): void
    {
        $providerUser = User::factory()->create();
        $provider = Provider::factory()->validated()->create(['user_id' => $providerUser->id]);
        $client = User::factory()->create();

        // 1. Tant qu'aucune mission n'a été confiée, le client ne peut PAS noter :
        //    c'est l'état dans lequel le produit était bloqué.
        Sanctum::actingAs($client);
        $this->postJson('/api/v1/reviews', [
            'reviewable_type' => 'provider',
            'reviewable_id' => $provider->id,
            'rating' => 5,
        ])->assertStatus(403);

        // 2. Un agent confie la mission depuis la fiche back-office.
        Sanctum::actingAs($this->agent());
        $mission = $this->postJson("/api/v1/providers/{$provider->id}/missions", [
            'title' => 'Guidage circuit Sine-Saloum',
            'amount_xof' => 250_000,
            'client_id' => $client->id,
        ])->assertCreated()->json('data.mission');

        // La commission est FIGÉE à l'affectation (le taux peut changer ensuite).
        $this->assertGreaterThan(0, $mission['commission_xof']);

        // 3. Elle apparaît sur la fiche — sans quoi on affecterait à l'aveugle.
        $this->getJson("/api/v1/admin/providers/{$provider->id}")
            ->assertOk()
            ->assertJsonPath('data.missions.0.reference', $mission['reference'])
            ->assertJsonPath('data.missions.0.client_name', $client->name);

        // 4. Le prestataire la mène à son terme depuis son espace.
        Sanctum::actingAs($providerUser);
        $this->patchJson("/api/v1/provider-missions/{$mission['id']}/accept")->assertOk();
        $this->patchJson("/api/v1/provider-missions/{$mission['id']}/start")->assertOk();
        $this->patchJson("/api/v1/provider-missions/{$mission['id']}/complete")
            ->assertOk()
            ->assertJsonPath('data.mission.status', 'terminee');

        // 5. LE POINT DU PARCOURS : le client peut enfin noter le prestataire.
        Sanctum::actingAs($client);
        $this->postJson('/api/v1/reviews', [
            'reviewable_type' => 'provider',
            'reviewable_id' => $provider->id,
            'rating' => 5,
            'comment' => 'Guide ponctuel et passionnant.',
        ])->assertCreated()->assertJsonPath('data.review.status', 'en_attente');
    }

    /**
     * Une mission encore en cours ne suffit pas : la preuve de consommation est
     * la mission TERMINÉE, pas la mission confiée.
     */
    public function test_une_mission_non_terminee_n_ouvre_pas_l_avis(): void
    {
        $providerUser = User::factory()->create();
        $provider = Provider::factory()->validated()->create(['user_id' => $providerUser->id]);
        $client = User::factory()->create();

        Sanctum::actingAs($this->agent());
        $mission = $this->postJson("/api/v1/providers/{$provider->id}/missions", [
            'title' => 'Navette aéroport',
            'amount_xof' => 40_000,
            'client_id' => $client->id,
        ])->assertCreated()->json('data.mission');

        Sanctum::actingAs($providerUser);
        $this->patchJson("/api/v1/provider-missions/{$mission['id']}/accept")->assertOk();

        Sanctum::actingAs($client);
        $this->postJson('/api/v1/reviews', [
            'reviewable_type' => 'provider',
            'reviewable_id' => $provider->id,
            'rating' => 4,
        ])->assertStatus(403);
    }

    /**
     * Un client qui n'a pas commandé la mission ne note pas le prestataire :
     * `client_id` est le lien de consommation, pas une décoration.
     */
    public function test_un_tiers_ne_note_pas_un_prestataire_qu_il_n_a_pas_employe(): void
    {
        $providerUser = User::factory()->create();
        $provider = Provider::factory()->validated()->create(['user_id' => $providerUser->id]);
        $client = User::factory()->create();
        $tiers = User::factory()->create();

        Sanctum::actingAs($this->agent());
        $mission = $this->postJson("/api/v1/providers/{$provider->id}/missions", [
            'title' => 'Location pirogue',
            'amount_xof' => 90_000,
            'client_id' => $client->id,
        ])->assertCreated()->json('data.mission');

        Sanctum::actingAs($providerUser);
        $this->patchJson("/api/v1/provider-missions/{$mission['id']}/accept")->assertOk();
        $this->patchJson("/api/v1/provider-missions/{$mission['id']}/start")->assertOk();
        $this->patchJson("/api/v1/provider-missions/{$mission['id']}/complete")->assertOk();

        Sanctum::actingAs($tiers);
        $this->postJson('/api/v1/reviews', [
            'reviewable_type' => 'provider',
            'reviewable_id' => $provider->id,
            'rating' => 1,
        ])->assertStatus(403);
    }
}
