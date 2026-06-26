<?php

namespace Tests\Feature\Immo;

use App\Models\Department;
use App\Models\Region;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Immo\Models\Property;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SenegalGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests de la gestion privée des biens par le propriétaire (phase B2.3).
 */
class PropertyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, SenegalGeographySeeder::class, CommunesSeeder::class]);
    }

    private function proprietaire(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::PROPRIETAIRE->value);

        return $user;
    }

    private function client(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::CLIENT->value);

        return $user;
    }

    /** Données de dépôt valides (région/département cohérents de Dakar). */
    private function donnees(array $surcharge = []): array
    {
        $region = Region::where('name', 'Dakar')->first();
        $department = $region->departments()->first();

        return array_merge([
            'type' => 'villa',
            'title' => 'Belle villa',
            'description' => 'Une belle villa.',
            'price_xof' => 50_000_000,
            'region_id' => $region->id,
            'department_id' => $department->id,
            'address' => 'Rue 10',
        ], $surcharge);
    }

    public function test_un_proprietaire_peut_deposer_un_bien(): void
    {
        $owner = $this->proprietaire();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/properties', $this->donnees())
            ->assertCreated()
            ->assertJsonPath('data.property.status', 'en_attente_validation')
            ->assertJsonPath('data.property.owner.id', $owner->id);

        $this->assertDatabaseHas('properties', [
            'title' => 'Belle villa',
            'owner_id' => $owner->id,
            'status' => 'en_attente_validation',
        ]);
    }

    public function test_le_depot_exige_le_role_proprietaire(): void
    {
        Sanctum::actingAs($this->client());

        $this->postJson('/api/v1/properties', $this->donnees())->assertStatus(403);
    }

    public function test_depot_avec_geo_incoherente_est_refuse(): void
    {
        Sanctum::actingAs($this->proprietaire());

        // Département de Thiès mais région = Dakar -> incohérent.
        $thiesDept = Department::whereHas('region', fn ($q) => $q->where('name', 'Thiès'))->first();

        $this->postJson('/api/v1/properties', $this->donnees(['department_id' => $thiesDept->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['department_id']);
    }

    public function test_un_proprietaire_voit_ses_biens_via_mine(): void
    {
        $owner = $this->proprietaire();
        $autre = $this->proprietaire();
        Property::factory()->count(2)->create(['owner_id' => $owner->id]);
        Property::factory()->create(['owner_id' => $autre->id]);

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/properties/mine')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_un_proprietaire_met_a_jour_son_bien(): void
    {
        $owner = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/properties/{$property->id}", ['title' => 'Nouveau titre'])
            ->assertOk()
            ->assertJsonPath('data.property.title', 'Nouveau titre');
    }

    public function test_un_proprietaire_ne_peut_pas_modifier_le_bien_d_un_autre(): void
    {
        $owner = $this->proprietaire();
        $autre = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $autre->id]);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/properties/{$property->id}", ['title' => 'Tentative'])
            ->assertStatus(403);
    }

    public function test_depot_de_document_sur_un_bien(): void
    {
        Storage::fake('local');
        $owner = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $file = UploadedFile::fake()->create('tf.pdf', 100, 'application/pdf');

        $res = $this->postJson("/api/v1/properties/{$property->id}/documents", [
            'type' => 'titre_foncier',
            'file' => $file,
        ]);

        $res->assertCreated()->assertJsonPath('data.document.type', 'titre_foncier');

        $document = $property->documents()->first();
        Storage::disk('local')->assertExists($document->path);
    }

    public function test_mine_exige_authentification(): void
    {
        $this->getJson('/api/v1/properties/mine')->assertStatus(401);
    }
}
