<?php

namespace Tests\Feature\Transversal;

use App\Models\User;
use App\Modules\Immo\Models\Property;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Stay\Models\Stay;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\SenegalGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests des favoris POLYMORPHES (tous univers) — couche transversale.
 *
 * Couvre les endpoints `/favorites*` : ajout par type (property/stay/vehicle/…),
 * refus des types inconnus ou des éléments non visibles, idempotence, retrait,
 * liste multi-univers, regroupement des ids, et l'isolation entre utilisateurs.
 */
class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // PropertyFactory s'appuie sur le référentiel géographique.
        $this->seed([SenegalGeographySeeder::class, CommunesSeeder::class]);
    }

    public function test_la_liste_exige_une_authentification(): void
    {
        $this->getJson('/api/v1/favorites')->assertStatus(401);
    }

    public function test_ajouter_un_bien_puis_un_vehicule(): void
    {
        $user = User::factory()->create();
        $property = Property::factory()->published()->create();
        $vehicle = Vehicle::factory()->published()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/favorites', ['type' => 'property', 'id' => $property->id])->assertOk();
        $this->postJson('/api/v1/favorites', ['type' => 'vehicle', 'id' => $vehicle->id])->assertOk();

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'favoritable_type' => Property::class,
            'favoritable_id' => $property->id,
        ]);
        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'favoritable_type' => Vehicle::class,
            'favoritable_id' => $vehicle->id,
        ]);
    }

    public function test_impossible_de_favoriser_un_element_non_visible(): void
    {
        $user = User::factory()->create();
        $draftProperty = Property::factory()->pending()->create();

        Sanctum::actingAs($user);

        // Bien non publié → 404 (on ne favorise que du visible).
        $this->postJson('/api/v1/favorites', ['type' => 'property', 'id' => $draftProperty->id])
            ->assertNotFound();
        // Élément inexistant → 404 également.
        $this->postJson('/api/v1/favorites', ['type' => 'vehicle', 'id' => 999999])
            ->assertNotFound();
    }

    public function test_un_type_inconnu_est_rejete(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/favorites', ['type' => 'licorne', 'id' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('type');
    }

    public function test_favoriser_est_idempotent(): void
    {
        $user = User::factory()->create();
        $property = Property::factory()->published()->create();

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/favorites', ['type' => 'property', 'id' => $property->id])->assertOk();
        $this->postJson('/api/v1/favorites', ['type' => 'property', 'id' => $property->id])->assertOk();

        $this->assertSame(1, $user->favorites()->count());
    }

    public function test_retirer_un_favori(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->published()->create();
        $user->favorites()->create([
            'favoritable_type' => Vehicle::class,
            'favoritable_id' => $vehicle->id,
        ]);

        Sanctum::actingAs($user);
        $this->deleteJson("/api/v1/favorites/vehicle/{$vehicle->id}")->assertOk();

        $this->assertSame(0, $user->favorites()->count());
    }

    public function test_la_liste_multi_univers(): void
    {
        $user = User::factory()->create();
        $property = Property::factory()->published()->create();
        $vehicle = Vehicle::factory()->published()->create();
        $stay = Stay::factory()->create();

        foreach ([[Property::class, $property->id], [Vehicle::class, $vehicle->id], [Stay::class, $stay->id]] as [$type, $id]) {
            $user->favorites()->create(['favoritable_type' => $type, 'favoritable_id' => $id]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/favorites')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'type', 'created_at', 'favoritable']]]);

        // Les trois univers sont représentés.
        $types = collect($response->json('data'))->pluck('type')->sort()->values()->all();
        $this->assertSame(['property', 'stay', 'vehicle'], $types);
    }

    public function test_les_ids_sont_groupes_par_type(): void
    {
        $user = User::factory()->create();
        $property = Property::factory()->published()->create();
        $vehicle = Vehicle::factory()->published()->create();
        $user->favorites()->create(['favoritable_type' => Property::class, 'favoritable_id' => $property->id]);
        $user->favorites()->create(['favoritable_type' => Vehicle::class, 'favoritable_id' => $vehicle->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/favorites/ids')
            ->assertOk()
            ->assertJsonPath('data.property', [$property->id])
            ->assertJsonPath('data.vehicle', [$vehicle->id])
            ->assertJsonPath('data.stay', []);
    }

    public function test_isolation_entre_utilisateurs(): void
    {
        $owner = User::factory()->create();
        $intrus = User::factory()->create();
        $property = Property::factory()->published()->create();
        $owner->favorites()->create(['favoritable_type' => Property::class, 'favoritable_id' => $property->id]);

        Sanctum::actingAs($intrus);

        // L'intrus ne voit pas les favoris d'autrui…
        $this->getJson('/api/v1/favorites')->assertOk()->assertJsonCount(0, 'data');
        // …et ne peut pas retirer le favori d'autrui (aucun effet sur le titulaire).
        $this->deleteJson("/api/v1/favorites/property/{$property->id}")->assertOk();
        $this->assertSame(1, $owner->favorites()->count());
    }
}
