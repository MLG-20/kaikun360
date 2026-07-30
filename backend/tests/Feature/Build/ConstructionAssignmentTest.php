<?php

namespace Tests\Feature\Build;

use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Build\Enums\ConstructionLot;
use App\Modules\Build\Models\ConstructionRequest;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Pro\Models\Provider;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests de l'affectation des prestataires BTP à un chantier (F7.3.e3 — CDC §6).
 *
 * Dernière exigence non couverte du module Construction. Chaque affectation crée
 * une mission Pro rattachée au chantier (cycle standard, commission figée), à un
 * LOT précis : un chantier fait intervenir plusieurs corps d'état.
 */
class ConstructionAssignmentTest extends TestCase
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

    public function test_un_agent_affecte_un_prestataire_valide_a_un_lot(): void
    {
        $request = ConstructionRequest::factory()->create();
        $provider = Provider::factory()->validated()->create();

        Sanctum::actingAs($this->agent());

        $response = $this->postJson("/api/v1/construction-requests/{$request->id}/assignments", [
            'provider_id' => $provider->id,
            'lot' => ConstructionLot::ELECTRICITE->value,
            'amount_xof' => 1_200_000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.mission.category', ConstructionLot::ELECTRICITE->value)
            ->assertJsonPath('data.mission.construction_request_id', $request->id)
            ->assertJsonPath('data.mission.status', 'affectee')
            ->assertJsonPath('data.mission.provider.business_name', $provider->business_name);

        // Le client du chantier devient le client de la mission : le prestataire
        // sait pour qui il intervient.
        $this->assertDatabaseHas('provider_missions', [
            'construction_request_id' => $request->id,
            'client_id' => $request->client_id,
        ]);
    }

    public function test_le_libelle_par_defaut_reprend_le_lot_et_la_reference(): void
    {
        $request = ConstructionRequest::factory()->create();
        $provider = Provider::factory()->validated()->create();

        Sanctum::actingAs($this->agent());

        $this->postJson("/api/v1/construction-requests/{$request->id}/assignments", [
            'provider_id' => $provider->id,
            'lot' => ConstructionLot::FONDATIONS->value,
            'amount_xof' => 800_000,
        ])->assertCreated()
            ->assertJsonPath('data.mission.title', 'Fondations — '.$request->reference);
    }

    public function test_la_commission_plateforme_est_figee_a_l_affectation(): void
    {
        $request = ConstructionRequest::factory()->create();
        $provider = Provider::factory()->validated()->create();

        Sanctum::actingAs($this->agent());

        $mission = $this->postJson("/api/v1/construction-requests/{$request->id}/assignments", [
            'provider_id' => $provider->id,
            'lot' => ConstructionLot::PLOMBERIE->value,
            'amount_xof' => 1_000_000,
        ])->assertCreated()->json('data.mission');

        // Commission par défaut 12 % (réglage `commission.default_rate`).
        $this->assertSame(120_000, $mission['commission_xof']);
        $this->assertSame(880_000, $mission['net_xof']);
    }

    public function test_plusieurs_corps_de_metier_cohabitent_sur_un_chantier(): void
    {
        $request = ConstructionRequest::factory()->create();
        $macon = Provider::factory()->validated()->create();
        $electricien = Provider::factory()->validated()->create();

        Sanctum::actingAs($this->agent());

        foreach ([[$macon, ConstructionLot::GROS_OEUVRE], [$electricien, ConstructionLot::ELECTRICITE]] as [$provider, $lot]) {
            $this->postJson("/api/v1/construction-requests/{$request->id}/assignments", [
                'provider_id' => $provider->id,
                'lot' => $lot->value,
                'amount_xof' => 500_000,
            ])->assertCreated();
        }

        $this->getJson("/api/v1/construction-requests/{$request->id}/assignments")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_un_prestataire_non_valide_est_refuse(): void
    {
        $request = ConstructionRequest::factory()->create();
        // Prestataire au dossier non validé : on ne l'envoie pas sur un chantier.
        $provider = Provider::factory()->create();

        Sanctum::actingAs($this->agent());

        $this->postJson("/api/v1/construction-requests/{$request->id}/assignments", [
            'provider_id' => $provider->id,
            'lot' => ConstructionLot::MENUISERIE->value,
            'amount_xof' => 300_000,
        ])->assertStatus(422);
    }

    public function test_un_lot_inconnu_est_refuse(): void
    {
        $request = ConstructionRequest::factory()->create();
        $provider = Provider::factory()->validated()->create();

        Sanctum::actingAs($this->agent());

        $this->postJson("/api/v1/construction-requests/{$request->id}/assignments", [
            'provider_id' => $provider->id,
            'lot' => 'piscine',
            'amount_xof' => 300_000,
        ])->assertStatus(422);
    }

    public function test_un_client_ne_peut_pas_affecter_de_prestataire(): void
    {
        $client = User::factory()->create();
        $request = ConstructionRequest::factory()->create(['client_id' => $client->id]);
        $provider = Provider::factory()->validated()->create();

        Sanctum::actingAs($client);

        $this->postJson("/api/v1/construction-requests/{$request->id}/assignments", [
            'provider_id' => $provider->id,
            'lot' => ConstructionLot::FINITIONS->value,
            'amount_xof' => 300_000,
        ])->assertForbidden();
    }

    public function test_le_client_voit_qui_intervient_sur_son_chantier(): void
    {
        $client = User::factory()->create();
        $request = ConstructionRequest::factory()->create(['client_id' => $client->id]);
        $provider = Provider::factory()->validated()->create();

        Sanctum::actingAs($this->agent());
        $this->postJson("/api/v1/construction-requests/{$request->id}/assignments", [
            'provider_id' => $provider->id,
            'lot' => ConstructionLot::CHARPENTE_COUVERTURE->value,
            'amount_xof' => 400_000,
        ])->assertCreated();

        // Lecture ouverte au client (policy `view`) : il a le droit de savoir qui
        // intervient chez lui.
        Sanctum::actingAs($client);
        $this->getJson("/api/v1/construction-requests/{$request->id}/assignments")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_une_mission_ordinaire_n_est_rattachee_a_aucun_chantier(): void
    {
        $provider = Provider::factory()->validated()->create();
        $mission = $provider->missions()->create([
            'reference' => 'MSN-ORDINAIRE',
            'client_id' => User::factory()->create()->id,
            'title' => 'Mission hors chantier',
            'amount_xof' => 100_000,
            'commission_xof' => 12_000,
            'status' => 'affectee',
        ]);

        // La migration est purement additive : rien ne casse pour l'existant.
        $this->assertNull($mission->construction_request_id);
        $this->assertNull($mission->team_building_request_id);
    }
}
