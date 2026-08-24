<?php

namespace Tests\Feature\Pro;

use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Pro\Models\Provider;
use App\Modules\Pro\Models\ProviderCategory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests F5 : catégories de service EXTENSIBLES — un prestataire qui ne se
 * reconnaît dans aucune catégorie peut en proposer une, réutilisable par les
 * autres une fois validée par un admin.
 */
class ProviderCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function actingAsProvider(): Provider
    {
        $user = User::factory()->create();
        $provider = Provider::factory()->validated()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        return $provider;
    }

    /** Agent disposant de la permission de valider les catégories. */
    private function agent(): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);
        $agent->givePermissionTo(AdminPermission::operational());

        return $agent;
    }

    public function test_la_liste_expose_les_categories_validees(): void
    {
        $this->actingAsProvider();

        $this->getJson('/api/v1/providers/categories')
            ->assertOk()
            ->assertJsonFragment(['key' => 'transport', 'status' => 'valide']);
    }

    public function test_le_prestataire_propose_une_categorie_et_l_utilise_aussitot(): void
    {
        $this->actingAsProvider();

        $this->postJson('/api/v1/providers/categories', ['label' => 'Photographie de mariage'])
            ->assertCreated()
            ->assertJsonPath('data.category.label', 'Photographie de mariage')
            ->assertJsonPath('data.category.status', 'en_attente');

        $this->assertDatabaseHas('provider_categories', [
            'key' => 'photographiedemariage',
            'status' => 'en_attente',
        ]);

        // Utilisable IMMÉDIATEMENT par son auteur, bien qu'en attente.
        $this->putJson('/api/v1/providers/mine', [
            'business_name' => 'Studio Lumière',
            'category' => 'photographiedemariage',
        ])->assertOk()->assertJsonPath('data.provider.category', 'photographiedemariage');
    }

    public function test_une_categorie_en_attente_d_un_autre_prestataire_est_refusee(): void
    {
        $author = $this->actingAsProvider();
        ProviderCategory::create([
            'key' => 'drone',
            'label' => 'Drone',
            'status' => 'en_attente',
            'created_by_provider_id' => $author->id,
        ]);

        // Un second prestataire tente d'assigner la catégorie encore en attente.
        $this->actingAsProvider();

        $this->putJson('/api/v1/providers/mine', [
            'business_name' => 'Peu importe',
            'category' => 'drone',
        ])->assertStatus(422)->assertJsonValidationErrors('category');
    }

    public function test_proposer_un_libelle_deja_existant_ne_duplique_pas(): void
    {
        $this->actingAsProvider();

        $this->postJson('/api/v1/providers/categories', ['label' => 'Transport']);
        $this->postJson('/api/v1/providers/categories', ['label' => 'Transport'])
            ->assertCreated()
            ->assertJsonPath('data.category.key', 'transport')
            ->assertJsonPath('data.category.status', 'valide');

        $this->assertDatabaseCount('provider_categories', 7); // les 7 historiques, aucun doublon
    }

    public function test_le_cycle_complet_d_approbation_rend_la_categorie_partagee(): void
    {
        $author = $this->actingAsProvider();
        $category = ProviderCategory::create([
            'key' => 'apiculture',
            'label' => 'Apiculture',
            'status' => 'en_attente',
            'created_by_provider_id' => $author->id,
        ]);

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/queue?type=provider_category')
            ->assertOk()
            ->assertJsonPath('data.0.id', $category->id)
            ->assertJsonPath('data.0.label', 'Apiculture');

        $this->patchJson("/api/v1/admin/validate/provider_category/{$category->id}", ['decision' => 'approve'])
            ->assertOk();

        $this->assertDatabaseHas('provider_categories', ['id' => $category->id, 'status' => 'valide']);

        // Désormais assignable par n'importe quel autre prestataire.
        $this->actingAsProvider();
        $this->putJson('/api/v1/providers/mine', [
            'business_name' => 'Rucher du Saloum',
            'category' => 'apiculture',
        ])->assertOk()->assertJsonPath('data.provider.category', 'apiculture');
    }
}
