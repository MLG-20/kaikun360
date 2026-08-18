<?php

namespace Tests\Feature\Admin;

use App\Models\NewsArticle;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests F15 : section « Actualités Kaikun » de l'accueil, pilotée au
 * back-office. Le cœur du parcours n'est pas le CRUD lui-même (déjà éprouvé
 * ailleurs) mais deux règles propres à cette tranche : l'image est
 * obligatoire, et la vidéo déposée l'emporte sur l'URL d'embed.
 */
class NewsArticleTest extends TestCase
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
        return UploadedFile::fake()->image('article.jpg', 1200, 800);
    }

    public function test_l_edition_est_reservee_a_gerer_parametres(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::AGENT_KAIKUN->value));

        $this->getJson('/api/v1/admin/news')->assertStatus(403);
        $this->postJson('/api/v1/admin/news', [])->assertStatus(403);
    }

    public function test_la_lecture_publique_ne_renvoie_que_les_articles_publies(): void
    {
        NewsArticle::factory()->create(['title' => 'Publié', 'is_published' => true]);
        NewsArticle::factory()->create(['title' => 'Brouillon', 'is_published' => false]);

        $this->getJson('/api/v1/news')
            ->assertOk()
            ->assertJsonCount(1, 'data.articles')
            ->assertJsonFragment(['title' => 'Publié']);
    }

    public function test_creer_un_article_exige_une_image(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->postJson('/api/v1/admin/news', ['title' => 'Sans image'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');

        $this->assertDatabaseCount('news_articles', 0);
    }

    public function test_creer_un_article_avec_image_et_url_video(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $response = $this->post('/api/v1/admin/news', [
            'title' => 'Nouveau bureau à Dakar',
            'excerpt' => 'Kaikun ouvre un bureau physique.',
            'image' => $this->image(),
            'video_url' => 'https://www.youtube.com/watch?v=abc123',
            'is_published' => true,
        ]);

        // Le lien « watch », copié tel quel depuis la barre d'adresse, est
        // normalisé en lien d'intégration (VideoEmbedUrl) — une page /watch
        // refuse d'être encadrée, seule /embed/ l'accepte.
        $response->assertCreated()
            ->assertJsonFragment(['video_url' => 'https://www.youtube.com/embed/abc123', 'video_file' => null]);

        $article = NewsArticle::firstOrFail();
        Storage::disk('public')->assertExists($article->image_path);
        $this->assertNull($article->video_path);
    }

    public function test_le_fichier_video_depose_l_emporte_sur_l_url(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $response = $this->post('/api/v1/admin/news', [
            'title' => 'Chantier filmé',
            'image' => $this->image(),
            'video' => UploadedFile::fake()->create('clip.mp4', 5000, 'video/mp4'),
            'video_url' => 'https://vimeo.com/999',
        ]);

        $response->assertCreated();

        $article = NewsArticle::firstOrFail();
        Storage::disk('public')->assertExists($article->video_path);
        // La ressource PUBLIQUE ne renvoie jamais les deux en même temps.
        $this->assertNotNull($article->fresh()->video_path);

        $public = $this->getJson('/api/v1/news')->json('data.articles');
        // L'article vient d'être créé sans `is_published` explicite → false,
        // absent de la liste publique. On le publie pour vérifier la priorité.
        $article->update(['is_published' => true]);
        $public = $this->getJson('/api/v1/news')->json('data.articles');
        $this->assertNotNull($public[0]['video_file']);
        $this->assertNull($public[0]['video_url']);
    }

    public function test_depublier_un_article_ne_le_supprime_pas(): void
    {
        $article = NewsArticle::factory()->create(['is_published' => true]);
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->post("/api/v1/admin/news/{$article->id}", ['is_published' => false])
            ->assertOk()
            ->assertJsonFragment(['is_published' => false]);

        $this->assertDatabaseHas('news_articles', ['id' => $article->id]);
        $this->getJson('/api/v1/news')->assertJsonCount(0, 'data.articles');
    }

    public function test_remplacer_l_image_supprime_l_ancien_fichier(): void
    {
        $article = NewsArticle::factory()->create();
        $ancien = $article->image_path;
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->post("/api/v1/admin/news/{$article->id}", ['image' => $this->image()])
            ->assertOk();

        Storage::disk('public')->assertMissing($ancien);
        Storage::disk('public')->assertExists($article->fresh()->image_path);
    }

    public function test_supprimer_un_article_efface_ses_fichiers(): void
    {
        $article = NewsArticle::factory()->create([
            'video_path' => 'news/clip.mp4',
        ]);
        Storage::disk('public')->put($article->image_path, 'x');
        Storage::disk('public')->put($article->video_path, 'x');
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->deleteJson("/api/v1/admin/news/{$article->id}")->assertNoContent();

        $this->assertDatabaseMissing('news_articles', ['id' => $article->id]);
        Storage::disk('public')->assertMissing($article->image_path);
        Storage::disk('public')->assertMissing($article->video_path);
    }
}
