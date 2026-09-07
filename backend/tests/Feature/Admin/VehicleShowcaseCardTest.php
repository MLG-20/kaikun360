<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\VehicleShowcaseCard;
use App\Modules\Core\Enums\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests F20 : section « Location de véhicules » de l'accueil, qui remplace
 * l'ancienne section « Protocole de confiance », pilotée au back-office.
 * Même patron que NewsArticleTest — les règles propres à cette tranche sont
 * que l'image ET le lien sont obligatoires (contrairement aux actualités, où
 * le lien est facultatif).
 */
class VehicleShowcaseCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->image('vehicule.jpg', 1200, 800);
    }

    public function test_l_edition_est_reservee_a_gerer_parametres(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::AGENT_KAIKUN->value));

        $this->getJson('/api/v1/admin/vehicle-showcase-cards')->assertStatus(403);
        $this->postJson('/api/v1/admin/vehicle-showcase-cards', [])->assertStatus(403);
    }

    public function test_la_lecture_publique_ne_renvoie_que_les_cartes_publiees(): void
    {
        VehicleShowcaseCard::factory()->create(['title' => 'Publiée', 'is_published' => true]);
        VehicleShowcaseCard::factory()->create(['title' => 'Brouillon', 'is_published' => false]);

        $this->getJson('/api/v1/vehicle-showcase-cards')
            ->assertOk()
            ->assertJsonCount(1, 'data.cards')
            ->assertJsonFragment(['title' => 'Publiée']);
    }

    public function test_creer_une_carte_exige_une_image(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->postJson('/api/v1/admin/vehicle-showcase-cards', [
            'title' => 'Sans image',
            'category' => 'Berline',
            'link_url' => 'https://kaikun360.com/transport',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');

        $this->assertDatabaseCount('vehicle_showcase_cards', 0);
    }

    public function test_creer_une_carte_exige_un_lien(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->post('/api/v1/admin/vehicle-showcase-cards', [
            'title' => 'Sans lien',
            'category' => 'Berline',
            'image' => $this->image(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('link_url');
    }

    public function test_creer_une_carte_exige_une_categorie(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->post('/api/v1/admin/vehicle-showcase-cards', [
            'title' => 'Sans catégorie',
            'image' => $this->image(),
            'link_url' => 'https://kaikun360.com/transport',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('category');
    }

    public function test_creer_une_carte_complete(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $response = $this->post('/api/v1/admin/vehicle-showcase-cards', [
            'title' => 'Berline climatisée',
            'description' => 'Confortable pour vos trajets en ville.',
            'category' => 'Berline',
            'image' => $this->image(),
            'link_url' => 'https://kaikun360.com/transport',
            'link_label' => 'Réserver',
            'is_published' => true,
        ]);

        $response->assertCreated()->assertJsonFragment([
            'title' => 'Berline climatisée',
            'category' => 'Berline',
            'link_url' => 'https://kaikun360.com/transport',
            'link_label' => 'Réserver',
        ]);

        $card = VehicleShowcaseCard::firstOrFail();
        Storage::disk('public')->assertExists($card->image_path);

        $public = $this->getJson('/api/v1/vehicle-showcase-cards')->json('data.cards');
        $this->assertSame('Berline climatisée', $public[0]['title']);
    }

    public function test_depublier_une_carte_ne_la_supprime_pas(): void
    {
        $card = VehicleShowcaseCard::factory()->create(['is_published' => true]);
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->post("/api/v1/admin/vehicle-showcase-cards/{$card->id}", ['is_published' => false])
            ->assertOk()
            ->assertJsonFragment(['is_published' => false]);

        $this->assertDatabaseHas('vehicle_showcase_cards', ['id' => $card->id]);
        $this->getJson('/api/v1/vehicle-showcase-cards')->assertJsonCount(0, 'data.cards');
    }

    public function test_remplacer_l_image_supprime_l_ancien_fichier(): void
    {
        $card = VehicleShowcaseCard::factory()->create();
        $ancien = $card->image_path;
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->post("/api/v1/admin/vehicle-showcase-cards/{$card->id}", ['image' => $this->image()])
            ->assertOk();

        Storage::disk('public')->assertMissing($ancien);
        Storage::disk('public')->assertExists($card->fresh()->image_path);
    }

    public function test_supprimer_une_carte_efface_son_image(): void
    {
        $card = VehicleShowcaseCard::factory()->create();
        Storage::disk('public')->put($card->image_path, 'x');
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->deleteJson("/api/v1/admin/vehicle-showcase-cards/{$card->id}")->assertNoContent();

        $this->assertDatabaseMissing('vehicle_showcase_cards', ['id' => $card->id]);
        Storage::disk('public')->assertMissing($card->image_path);
    }

    public function test_les_cartes_publiques_sont_triees_par_position(): void
    {
        VehicleShowcaseCard::factory()->create(['title' => 'Seconde', 'is_published' => true, 'position' => 2]);
        VehicleShowcaseCard::factory()->create(['title' => 'Première', 'is_published' => true, 'position' => 1]);

        $titres = collect($this->getJson('/api/v1/vehicle-showcase-cards')->json('data.cards'))
            ->pluck('title')
            ->all();

        $this->assertSame(['Première', 'Seconde'], $titres);
    }

    // ── Accroche de la section (réglages home.vehicle_showcase_*) ─────────

    public function test_l_accroche_par_defaut_est_servie_sans_reglage_enregistre(): void
    {
        $data = $this->getJson('/api/v1/vehicle-showcase-cards')->json('data');

        $this->assertSame('Location de véhicules', $data['eyebrow']);
        $this->assertNotEmpty($data['title']);
        $this->assertNotEmpty($data['lead']);
    }

    public function test_l_accroche_est_modifiable_au_back_office(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->patchJson('/api/v1/admin/settings', [
            'settings' => ['home.vehicle_showcase_title' => 'Notre flotte, vérifiée pour vous.'],
        ])->assertOk();

        $this->getJson('/api/v1/vehicle-showcase-cards')
            ->assertJsonPath('data.title', 'Notre flotte, vérifiée pour vous.');
    }
}
