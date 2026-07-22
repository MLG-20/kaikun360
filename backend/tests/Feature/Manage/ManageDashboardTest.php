<?php

namespace Tests\Feature\Manage;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Manage\Models\Expense;
use App\Modules\Manage\Models\Incident;
use App\Modules\Manage\Models\ManagementMandate;
use App\Modules\Manage\Models\OwnerPayout;
use App\Modules\Manage\Models\Rent;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests de l'espace propriétaire (gestion locative, phase B4.5) :
 * isolation par propriétaire et exactitude des agrégats du tableau de bord.
 */
class ManageDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_un_proprietaire_ne_voit_que_ses_mandats(): void
    {
        $owner = User::factory()->create();
        ManagementMandate::factory()->count(2)->create(['owner_id' => $owner->id]);
        ManagementMandate::factory()->create(); // mandat d'un autre propriétaire

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/manage/mandates/mine')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_le_tableau_de_bord_agrege_les_donnees_du_proprietaire(): void
    {
        $owner = User::factory()->create();
        $mandate = ManagementMandate::factory()->create(['owner_id' => $owner->id]);

        Rent::factory()->paid()->create(['mandate_id' => $mandate->id, 'amount_xof' => 100_000]);
        Rent::factory()->create(['mandate_id' => $mandate->id, 'amount_xof' => 50_000, 'status' => 'impaye']);
        Expense::factory()->create(['property_id' => $mandate->property_id, 'amount_xof' => 30_000]);
        OwnerPayout::factory()->done()->create([
            'mandate_id' => $mandate->id,
            'owner_id' => $owner->id,
            'amount_xof' => 70_000,
        ]);
        Incident::factory()->create(['property_id' => $mandate->property_id, 'status' => 'ouvert']);

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/manage/dashboard')
            ->assertOk()
            ->assertJsonPath('data.loyers_payes_xof', 100_000)
            ->assertJsonPath('data.loyers_impayes_xof', 50_000)
            ->assertJsonPath('data.depenses_xof', 30_000)
            ->assertJsonPath('data.reversements_xof', 70_000)
            ->assertJsonPath('data.incidents_ouverts', 1);
    }

    public function test_les_agregats_apparaissent_dans_la_liste_mine(): void
    {
        $owner = User::factory()->create();
        $mandate = ManagementMandate::factory()->create(['owner_id' => $owner->id]);
        Rent::factory()->count(2)->paid()->create(['mandate_id' => $mandate->id, 'amount_xof' => 120_000]);
        Rent::factory()->create(['mandate_id' => $mandate->id, 'amount_xof' => 120_000]); // impayé

        Sanctum::actingAs($owner);

        // Montants ET nombre d'échéances (le compte lève l'ambiguïté de deux
        // mois au même loyer : 240 000 encaissés = 2 échéances, 120 000 dus = 1).
        $this->getJson('/api/v1/manage/mandates/mine')
            ->assertOk()
            ->assertJsonPath('data.0.summary.loyers_payes_xof', 240_000)
            ->assertJsonPath('data.0.summary.loyers_payes_count', 2)
            ->assertJsonPath('data.0.summary.loyers_impayes_xof', 120_000)
            ->assertJsonPath('data.0.summary.loyers_impayes_count', 1);
    }

    public function test_un_proprietaire_ne_peut_pas_voir_le_mandat_d_un_autre(): void
    {
        $owner = User::factory()->create();
        $mandate = ManagementMandate::factory()->create(); // appartient à un autre

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/manage/mandates/{$mandate->id}")->assertStatus(403);
    }

    public function test_un_agent_peut_voir_n_importe_quel_mandat(): void
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);
        $mandate = ManagementMandate::factory()->create();

        Sanctum::actingAs($agent);

        $this->getJson("/api/v1/manage/mandates/{$mandate->id}")
            ->assertOk()
            ->assertJsonPath('data.mandate.id', $mandate->id);
    }

    public function test_la_fiche_mandat_expose_ses_lignes_loyers_reversements_incidents(): void
    {
        $owner = User::factory()->create();
        $mandate = ManagementMandate::factory()->create(['owner_id' => $owner->id]);

        Rent::factory()->paid()->create(['mandate_id' => $mandate->id, 'amount_xof' => 100_000]);
        OwnerPayout::factory()->done()->create([
            'mandate_id' => $mandate->id,
            'owner_id' => $owner->id,
            'amount_xof' => 70_000,
        ]);
        Incident::factory()->create(['property_id' => $mandate->property_id, 'status' => 'ouvert']);

        // Lignes d'un AUTRE mandat : ne doivent pas fuiter dans cette fiche.
        $other = ManagementMandate::factory()->create();
        Rent::factory()->create(['mandate_id' => $other->id]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/manage/mandates/{$mandate->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.mandate.rents')
            ->assertJsonCount(1, 'data.mandate.payouts')
            ->assertJsonCount(1, 'data.mandate.incidents')
            ->assertJsonPath('data.mandate.rents.0.amount_xof', 100_000)
            ->assertJsonPath('data.mandate.payouts.0.amount_xof', 70_000);
    }

    public function test_la_liste_mine_n_embarque_pas_les_lignes_detaillees(): void
    {
        $owner = User::factory()->create();
        $mandate = ManagementMandate::factory()->create(['owner_id' => $owner->id]);
        Rent::factory()->create(['mandate_id' => $mandate->id]);

        Sanctum::actingAs($owner);

        // La liste reste légère : agrégats oui, lignes non (clé absente).
        $this->getJson('/api/v1/manage/mandates/mine')
            ->assertOk()
            ->assertJsonMissingPath('data.0.rents');
    }

    public function test_l_espace_gestion_exige_une_authentification(): void
    {
        $this->getJson('/api/v1/manage/dashboard')->assertStatus(401);
    }
}
