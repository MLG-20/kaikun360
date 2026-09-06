<?php

use App\Models\NewsArticle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Ajoute `category` et `slug` à `news_articles` (demande client 2026-09-06,
 * page « Actualités » digne de ce nom, réutilisable pour toute future actu).
 *
 * `category` est un texte libre saisi par l'équipe (pas de table à part — la
 * page publique calcule elle-même les catégories distinctes utilisées).
 *
 * `slug` sert des URLs lisibles (`/actualites/mon-titre`) tout en gardant
 * `/actualites/{id}` fonctionnel (déjà indexé par Google Search Console) —
 * voir `NewsArticle::resolveRouteBinding()`. Les lignes déjà en base (seeder
 * ou articles réels) n'ont pas de slug : on le calcule ici avant de poser la
 * contrainte unique, pour qu'aucun article existant ne se retrouve orphelin
 * d'URL lisible après la migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->string('category', 60)->nullable()->after('excerpt');
            $table->string('slug', 220)->nullable()->after('title');
        });

        NewsArticle::whereNull('slug')->get()->each(function (NewsArticle $article): void {
            $base = Str::slug($article->title) ?: 'article';
            $slug = $base;
            $suffix = 2;

            while (NewsArticle::where('slug', $slug)->where('id', '!=', $article->id)->exists()) {
                $slug = "{$base}-{$suffix}";
                $suffix++;
            }

            // forceFill(), pas update() : `slug` est volontairement absent
            // de $fillable (généré côté serveur uniquement, voir
            // NewsArticle) — un update() mass-assigné l'ignorerait en
            // silence, laissant CHAQUE article déjà en base sans slug (bug
            // constaté en recette locale). saveQuietly() pour ne pas
            // redéclencher le hook `saving` du modèle sur cette écriture.
            $article->forceFill(['slug' => $slug])->saveQuietly();
        });

        Schema::table('news_articles', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['category', 'slug']);
        });
    }
};
