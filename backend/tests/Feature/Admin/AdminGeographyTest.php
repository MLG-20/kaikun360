<?php

namespace Tests\Feature\Admin;

use App\Models\Commune;
use App\Models\Department;
use App\Models\Region;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Immo\Models\Property;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests F7.2.l : administration du référentiel géographique — les « villes » du
 * CDC §6 (module *Paramètres*).
 *
 * L'enjeu n'est pas le CRUD lui-même mais ses GARDE-FOUS : les FK
 * `properties.commune_id` / `users.commune_id` sont en `nullOnDelete` et
 * `communes.department_id` en `cascadeOnDelete`. Sans refus explicite, une
 * suppression au back-office effacerait des localisations de biens, ou des
 * dizaines de communes d'un coup, SANS le moindre message.
 */
class AdminGeographyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** Un département rattaché à une région fraîche. */
    private function department(string $name = 'Thiès'): Department
    {
        return Department::create([
            'region_id' => Region::create(['name' => 'Région '.$name])->id,
            'name' => $name,
        ]);
    }

    public function test_l_acces_est_reserve_a_gerer_parametres(): void
    {
        // L'agent porte consulter:dashboard-admin, pas gerer:parametres.
        Sanctum::actingAs($this->withRole(UserRole::AGENT_KAIKUN->value));

        $this->getJson('/api/v1/admin/geography')->assertStatus(403);
        $this->postJson('/api/v1/admin/communes', [])->assertStatus(403);
    }

    public function test_l_arborescence_expose_les_compteurs_par_region_et_departement(): void
    {
        $department = $this->department();
        Commune::create(['department_id' => $department->id, 'name' => 'Mbour']);
        Commune::create(['department_id' => $department->id, 'name' => 'Saly']);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $response = $this->getJson('/api/v1/admin/geography')->assertOk();

        $region = collect($response->json('data.regions'))
            ->firstWhere('id', $department->region_id);

        $this->assertSame(1, $region['departments_count']);
        // Le total région est la somme des communes de ses départements.
        $this->assertSame(2, $region['communes_count']);
        $this->assertSame(2, $region['departments'][0]['communes_count']);
    }

    public function test_liste_les_communes_avec_leur_usage_et_leur_supprimabilite(): void
    {
        $department = $this->department();
        $used = Commune::create(['department_id' => $department->id, 'name' => 'Mbour']);
        $free = Commune::create(['department_id' => $department->id, 'name' => 'Saly']);

        Property::factory()->create(['commune_id' => $used->id]);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $rows = collect($this->getJson('/api/v1/admin/communes?department_id='.$department->id)
            ->assertOk()
            ->json('data'));

        $this->assertSame(1, $rows->firstWhere('id', $used->id)['properties_count']);
        $this->assertFalse($rows->firstWhere('id', $used->id)['deletable']);
        $this->assertTrue($rows->firstWhere('id', $free->id)['deletable']);
        // Le libellé du parent remonte avec la ligne (pas de second appel).
        $this->assertSame($department->name, $rows->firstWhere('id', $free->id)['department_name']);
    }

    public function test_filtre_les_communes_par_region_et_par_recherche(): void
    {
        $dakar = $this->department('Dakar');
        $thies = $this->department('Thiès');
        Commune::create(['department_id' => $dakar->id, 'name' => 'Ouakam']);
        Commune::create(['department_id' => $thies->id, 'name' => 'Mbour']);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        // Le filtre région passe par le département parent.
        $this->getJson('/api/v1/admin/communes?region_id='.$dakar->region_id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Ouakam']);

        $this->getJson('/api/v1/admin/communes?q=Mbo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Mbour']);
    }

    public function test_cree_une_commune_et_refuse_un_doublon_dans_le_meme_departement(): void
    {
        $department = $this->department();

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->postJson('/api/v1/admin/communes', [
            'department_id' => $department->id,
            'name' => 'Mbour',
            'type' => 'commune',
        ])->assertCreated()->assertJsonPath('data.commune.name', 'Mbour');

        // Même nom, même département → 422 (et non une erreur SQL sur l'index).
        $this->postJson('/api/v1/admin/communes', [
            'department_id' => $department->id,
            'name' => 'Mbour',
        ])->assertStatus(422)->assertJsonValidationErrors('name');

        // Même nom dans un AUTRE département → accepté (l'unicité est locale).
        $this->postJson('/api/v1/admin/communes', [
            'department_id' => $this->department('Fatick')->id,
            'name' => 'Mbour',
        ])->assertCreated();
    }

    public function test_renomme_une_commune_sans_buter_sur_sa_propre_unicite(): void
    {
        $department = $this->department();
        $commune = Commune::create(['department_id' => $department->id, 'name' => 'Mbour']);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        // Modifier le seul `type` ne doit pas déclencher le doublon sur son
        // propre nom (piège classique de la règle unique sans `ignore`).
        $this->patchJson("/api/v1/admin/communes/{$commune->id}", ['type' => 'commune d’arrondissement'])
            ->assertOk()
            ->assertJsonPath('data.commune.type', 'commune d’arrondissement');

        $this->patchJson("/api/v1/admin/communes/{$commune->id}", ['name' => 'Mbour Sérère'])
            ->assertOk()
            ->assertJsonPath('data.commune.name', 'Mbour Sérère');
    }

    public function test_refuse_de_supprimer_une_commune_encore_utilisee(): void
    {
        $department = $this->department();
        $commune = Commune::create(['department_id' => $department->id, 'name' => 'Mbour']);
        Property::factory()->create(['commune_id' => $commune->id]);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->deleteJson("/api/v1/admin/communes/{$commune->id}")->assertStatus(409);

        // La commune est toujours là : rien n'a été perdu en silence.
        $this->assertDatabaseHas('communes', ['id' => $commune->id]);
    }

    public function test_supprime_une_commune_libre(): void
    {
        $department = $this->department();
        $commune = Commune::create(['department_id' => $department->id, 'name' => 'Saly']);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->deleteJson("/api/v1/admin/communes/{$commune->id}")->assertNoContent();

        $this->assertDatabaseMissing('communes', ['id' => $commune->id]);
    }

    public function test_refuse_de_supprimer_un_departement_qui_contient_des_communes(): void
    {
        $department = $this->department();
        Commune::create(['department_id' => $department->id, 'name' => 'Saly']);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        // Sans ce refus, le cascadeOnDelete emporterait la commune sans alerte.
        $this->deleteJson("/api/v1/admin/departments/{$department->id}")->assertStatus(409);

        $this->assertDatabaseHas('communes', ['department_id' => $department->id]);
    }

    public function test_gere_le_cycle_complet_d_un_departement(): void
    {
        $region = Region::create(['name' => 'Kaolack']);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $id = $this->postJson('/api/v1/admin/departments', [
            'region_id' => $region->id,
            'name' => 'Nioro',
        ])->assertCreated()->json('data.department.id');

        $this->patchJson("/api/v1/admin/departments/{$id}", ['name' => 'Nioro du Rip'])
            ->assertOk()
            ->assertJsonPath('data.department.name', 'Nioro du Rip');

        $this->deleteJson("/api/v1/admin/departments/{$id}")->assertNoContent();

        $this->assertDatabaseMissing('departments', ['id' => $id]);
    }
}
