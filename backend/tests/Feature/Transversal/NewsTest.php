<?php

namespace Tests\Feature\Transversal;

use App\Models\NewsArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Détail public d'une actualité (F16.3) — route `/api/v1/news/{id}`.
 *
 * La liste (`GET /news`) ne renvoie que titre et résumé côté carte de
 * l'accueil ; ces tests verrouillent que le corps complet est bien atteignable
 * par son propre endpoint, et qu'un article non publié reste invisible.
 */
class NewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_detail_d_un_article_publie_est_servi_publiquement(): void
    {
        $article = NewsArticle::factory()->create([
            'is_published' => true,
            'body' => '<p>Corps complet de l\'article.</p>',
        ]);

        $this->getJson("/api/v1/news/{$article->id}")
            ->assertOk()
            ->assertJsonPath('data.article.id', $article->id)
            ->assertJsonPath('data.article.title', $article->title)
            ->assertJsonPath('data.article.body', $article->body);
    }

    public function test_un_article_non_publie_repond_404(): void
    {
        $article = NewsArticle::factory()->create(['is_published' => false]);

        $this->getJson("/api/v1/news/{$article->id}")->assertNotFound();
    }

    public function test_un_identifiant_inexistant_repond_404(): void
    {
        $this->getJson('/api/v1/news/999999')->assertNotFound();
    }
}
