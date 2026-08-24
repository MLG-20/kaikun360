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

    public function test_mine_inclut_tous_les_statuts(): void
    {
        $owner = $this->proprietaire();
        Property::factory()->published()->create(['owner_id' => $owner->id]);
        Property::factory()->pending()->create(['owner_id' => $owner->id]);
        Property::factory()->rejected()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $statuses = collect(
            $this->getJson('/api/v1/properties/mine')->assertOk()->json('data'),
        )->pluck('status')->all();

        // Le propriétaire voit ses biens quel que soit leur statut (pas seulement publiés).
        $this->assertContains('publie', $statuses);
        $this->assertContains('en_attente_validation', $statuses);
        $this->assertContains('rejete', $statuses);
    }

    public function test_un_proprietaire_voit_la_fiche_de_son_bien_non_publie(): void
    {
        $owner = $this->proprietaire();
        $property = Property::factory()->pending()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/properties/mine/{$property->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $property->id)
            ->assertJsonPath('data.status', 'en_attente_validation');
    }

    public function test_la_fiche_d_un_bien_d_un_autre_renvoie_404(): void
    {
        $owner = $this->proprietaire();
        $autre = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $autre->id]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/properties/mine/{$property->id}")->assertStatus(404);
    }

    public function test_la_fiche_exige_authentification(): void
    {
        $property = Property::factory()->create();

        $this->getJson("/api/v1/properties/mine/{$property->id}")->assertStatus(401);
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

    public function test_le_proprietaire_declare_une_caution_dont_le_total_est_calcule(): void
    {
        $owner = $this->proprietaire();
        Sanctum::actingAs($owner);

        // Montant MENSUEL déclaré × nombre de mois → total calculé (50 000 × 2).
        $this->postJson('/api/v1/properties', $this->donnees([
            'caution_xof' => 50_000,
            'caution_months' => 2,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.property.caution_xof', 50_000)
            ->assertJsonPath('data.property.caution_months', 2)
            ->assertJsonPath('data.property.caution_total_xof', 100_000);

        $this->assertDatabaseHas('properties', ['caution_xof' => 50_000, 'caution_months' => 2]);
    }

    public function test_la_caution_est_plafonnee_a_douze_mois(): void
    {
        $owner = $this->proprietaire();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/properties', $this->donnees(['caution_months' => 13]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('caution_months');
    }

    public function test_la_caution_se_modifie_independamment_du_loyer(): void
    {
        $owner = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $owner->id, 'caution_xof' => null]);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/properties/{$property->id}", ['caution_xof' => 150_000_000])
            ->assertOk()
            ->assertJsonPath('data.property.caution_xof', 150_000_000);
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

    public function test_liste_des_documents_dun_bien(): void
    {
        Storage::fake('local');
        $owner = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($owner);

        // Deux dépôts, puis on liste.
        $this->postJson("/api/v1/properties/{$property->id}/documents", [
            'type' => 'titre_foncier',
            'file' => UploadedFile::fake()->create('tf.pdf', 100, 'application/pdf'),
        ])->assertCreated();
        $this->postJson("/api/v1/properties/{$property->id}/documents", [
            'type' => 'bail',
            'file' => UploadedFile::fake()->create('bail.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $res = $this->getJson("/api/v1/properties/{$property->id}/documents");

        $res->assertOk()->assertJsonCount(2, 'data');
        // Un lien de téléchargement SIGNÉ est fourni, jamais le chemin brut.
        $res->assertJsonPath('data.0.download_url', fn ($url) => is_string($url) && str_contains($url, 'signature='));
        $res->assertJsonMissingPath('data.0.path');
    }

    public function test_liste_des_documents_isolee_par_proprietaire(): void
    {
        $owner = $this->proprietaire();
        $autre = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($autre);

        $this->getJson("/api/v1/properties/{$property->id}/documents")->assertStatus(403);
    }

    public function test_suppression_dun_document(): void
    {
        Storage::fake('local');
        $owner = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/properties/{$property->id}/documents", [
            'type' => 'plan',
            'file' => UploadedFile::fake()->create('plan.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $document = $property->documents()->first();
        Storage::disk('local')->assertExists($document->path);

        $this->deleteJson("/api/v1/properties/{$property->id}/documents/{$document->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        // Ligne ET fichier physique retirés.
        $this->assertDatabaseMissing('property_documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($document->path);
    }

    public function test_suppression_de_document_refusee_a_un_tiers(): void
    {
        Storage::fake('local');
        $owner = $this->proprietaire();
        $autre = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/properties/{$property->id}/documents", [
            'type' => 'autre',
            'file' => UploadedFile::fake()->create('x.pdf', 100, 'application/pdf'),
        ])->assertCreated();
        $document = $property->documents()->first();

        // Un autre propriétaire n'a aucun droit sur ce bien.
        Sanctum::actingAs($autre);
        $this->deleteJson("/api/v1/properties/{$property->id}/documents/{$document->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('property_documents', ['id' => $document->id]);
    }

    public function test_mine_expose_le_compteur_de_documents(): void
    {
        Storage::fake('local');
        $owner = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/properties/{$property->id}/documents", [
            'type' => 'titre_foncier',
            'file' => UploadedFile::fake()->create('tf.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $this->getJson('/api/v1/properties/mine')
            ->assertOk()
            ->assertJsonPath('data.0.documents_count', 1);
    }

    public function test_mine_exige_authentification(): void
    {
        $this->getJson('/api/v1/properties/mine')->assertStatus(401);
    }
}
