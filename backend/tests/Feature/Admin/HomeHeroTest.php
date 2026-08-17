<?php

namespace Tests\Feature\Admin;

use App\Models\HomeHeroSlide;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests F15.1 : héros de l'accueil (diaporama de photos, ou une vidéo à la
 * place). Distinct des bandeaux F12 — pas d'héritage à vérifier ici, mais deux
 * règles propres à cette tranche : les photos s'ajoutent en file (ordre
 * d'arrivée), et la vidéo est un singleton dont le fichier l'emporte sur le
 * lien d'embed.
 */
class HomeHeroTest extends TestCase
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
        return UploadedFile::fake()->image('slide.jpg', 1920, 900);
    }

    public function test_l_edition_est_reservee_a_gerer_parametres(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::AGENT_KAIKUN->value));

        $this->getJson('/api/v1/admin/home-hero')->assertStatus(403);
        $this->postJson('/api/v1/admin/home-hero/slides', [])->assertStatus(403);
    }

    public function test_la_lecture_publique_est_vide_par_defaut(): void
    {
        $this->getJson('/api/v1/home-hero')
            ->assertOk()
            ->assertJson(['data' => ['images' => [], 'video' => null]]);
    }

    public function test_ajouter_des_photos_les_met_en_file_dans_l_ordre(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->post('/api/v1/admin/home-hero/slides', ['image' => $this->image()])->assertCreated();
        $this->post('/api/v1/admin/home-hero/slides', ['image' => $this->image()])->assertCreated();

        $slides = HomeHeroSlide::ordered()->get();
        $this->assertCount(2, $slides);
        $this->assertSame(0, $slides[0]->position);
        $this->assertSame(1, $slides[1]->position);

        $public = $this->getJson('/api/v1/home-hero')->json('data.images');
        $this->assertCount(2, $public);
    }

    public function test_retirer_une_photo_supprime_son_fichier(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));
        $this->post('/api/v1/admin/home-hero/slides', ['image' => $this->image()]);
        $slide = HomeHeroSlide::firstOrFail();

        $this->deleteJson("/api/v1/admin/home-hero/slides/{$slide->id}")->assertOk();

        $this->assertDatabaseMissing('home_hero_slides', ['id' => $slide->id]);
        Storage::disk('public')->assertMissing($slide->image_path);
    }

    public function test_deposer_un_fichier_video_l_emporte_sur_un_lien_deja_enregistre(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));
        $this->post('/api/v1/admin/home-hero/video', ['video_url' => 'https://www.youtube.com/watch?v=abc']);

        $this->post('/api/v1/admin/home-hero/video', [
            'video' => UploadedFile::fake()->create('clip.mp4', 5000, 'video/mp4'),
        ])->assertOk();

        // ⚠️ `Settings::set(..., null)` stocke une chaîne vide, pas NULL (le
        // dépôt sérialise toute valeur en texte) — `?: null` reflète ce que le
        // contrôleur applique lui-même à la lecture.
        $this->assertNull(Settings::get('home.hero_video_url') ?: null);
        $this->assertNotNull(Settings::get('home.hero_video_path') ?: null);

        $public = $this->getJson('/api/v1/home-hero')->json('data.video');
        $this->assertNotNull($public['file']);
        $this->assertNull($public['url']);
    }

    public function test_retirer_la_video_supprime_le_fichier_et_les_reglages(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));
        $this->post('/api/v1/admin/home-hero/video', [
            'video' => UploadedFile::fake()->create('clip.mp4', 5000, 'video/mp4'),
        ]);
        $path = Settings::get('home.hero_video_path');

        $this->post('/api/v1/admin/home-hero/video', ['remove_video' => '1'])->assertOk();

        $this->assertNull(Settings::get('home.hero_video_path') ?: null);
        Storage::disk('public')->assertMissing($path);
        $this->getJson('/api/v1/home-hero')->assertJson(['data' => ['video' => null]]);
    }
}
