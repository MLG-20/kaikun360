<?php

namespace Tests\Feature\Geo;

use App\Models\Department;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\SenegalGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Endpoints publics du référentiel géographique (F2.7.0).
 *
 * Vérifie que le frontend peut lire régions / départements / communes pour
 * alimenter les sélecteurs en cascade du dépôt de bien, et que les listes
 * enfants sont TOUJOURS filtrées par leur parent (paramètre obligatoire).
 */
class GeoReferentialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SenegalGeographySeeder::class, CommunesSeeder::class]);
    }

    public function test_les_regions_sont_lisibles_publiquement(): void
    {
        $response = $this->getJson('/api/v1/regions');

        $response->assertOk()
            ->assertJsonCount(14, 'data')
            ->assertJsonStructure(['data' => [['id', 'name']]]);
    }

    public function test_les_departements_sont_filtres_par_region(): void
    {
        $dakar = Region::where('name', 'Dakar')->firstOrFail();

        $response = $this->getJson('/api/v1/departments?region_id='.$dakar->id);

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('region_id')->unique();
        // Tous les départements renvoyés appartiennent bien à la région demandée.
        $this->assertSame([$dakar->id], $ids->all());
        $this->assertSame($dakar->departments()->count(), count($response->json('data')));
    }

    public function test_le_departement_est_obligatoire_pour_lister_les_communes(): void
    {
        $this->getJson('/api/v1/communes')->assertStatus(422);
    }

    public function test_la_region_est_obligatoire_pour_lister_les_departements(): void
    {
        $this->getJson('/api/v1/departments')->assertStatus(422);
    }

    public function test_les_communes_sont_filtrees_par_departement(): void
    {
        $dakar = Department::whereHas('region', fn ($q) => $q->where('name', 'Dakar'))
            ->where('name', 'Dakar')
            ->firstOrFail();

        $response = $this->getJson('/api/v1/communes?department_id='.$dakar->id);

        $response->assertOk()
            ->assertJsonCount(19, 'data')
            ->assertJsonStructure(['data' => [['id', 'department_id', 'name', 'type']]]);
    }

    public function test_un_departement_inexistant_est_rejete(): void
    {
        $this->getJson('/api/v1/communes?department_id=999999')->assertStatus(422);
    }

    public function test_un_utilisateur_connecte_propose_une_commune_manquante(): void
    {
        $dakar = Department::whereHas('region', fn ($q) => $q->where('name', 'Dakar'))
            ->where('name', 'Dakar')
            ->firstOrFail();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/communes', [
            'department_id' => $dakar->id,
            'name' => 'Une commune non listée',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Une commune non listée')
            ->assertJsonPath('data.department_id', $dakar->id);

        // Aucune modération : immédiatement visible pour tout le monde.
        $this->getJson('/api/v1/communes?department_id='.$dakar->id)
            ->assertOk()
            ->assertJsonFragment(['name' => 'Une commune non listée']);
    }

    public function test_proposer_une_commune_exige_une_authentification(): void
    {
        $dakar = Department::whereHas('region', fn ($q) => $q->where('name', 'Dakar'))
            ->where('name', 'Dakar')->firstOrFail();

        $this->postJson('/api/v1/communes', [
            'department_id' => $dakar->id,
            'name' => 'Une commune',
        ])->assertStatus(401);
    }

    public function test_un_nom_deja_pris_dans_le_departement_est_rejete(): void
    {
        $dakar = Department::whereHas('region', fn ($q) => $q->where('name', 'Dakar'))
            ->where('name', 'Dakar')->firstOrFail();
        $existante = $dakar->communes()->first();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/communes', [
            'department_id' => $dakar->id,
            'name' => $existante->name,
        ])->assertStatus(422)->assertJsonValidationErrors('name');
    }
}
