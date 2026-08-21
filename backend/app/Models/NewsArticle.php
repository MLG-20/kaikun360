<?php

namespace App\Models;

use Database\Factories\NewsArticleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Article de la section « Actualités Kaikun » de la page d'accueil (F15).
 *
 * L'image est la seule chose obligatoire : c'est elle qui porte la carte dans
 * la grille. La vidéo est facultative et a deux formes exclusives à
 * l'affichage — un fichier déposé (déjà compressé côté équipe, aucun
 * transcodage ici) ou une URL d'embed — le fichier l'emporte si les deux sont
 * saisis.
 *
 * ⚠️ **`link_url`/`link_label` (F17, 2026-08-21)** : la plateforme
 * n'appartient pas à l'équipe — le client doit pouvoir présenter une carte
 * dans cette même section SANS rédiger un article (`body` reste vide) : une
 * image, un titre, et un lien de son choix (vers `/immobilier`, une offre du
 * moment, n'importe quoi). Pas un second système de contenu à côté des
 * actualités : la MÊME ligne, le MÊME écran d'édition, le MÊME rendu public —
 * seul le bouton change de destination. Voir `HomePageComponent` (frontend)
 * pour le repli : sans `link_url`, le bouton pointe vers l'article lui-même.
 */
class NewsArticle extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'excerpt',
        'body',
        'image_path',
        'video_path',
        'video_url',
        'link_url',
        'link_label',
        'is_published',
        'position',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /**
     * URL de la vidéo déposée, ou `null` si aucun fichier n'a été chargé.
     */
    public function videoFileUrl(): ?string
    {
        return $this->video_path ? Storage::disk('public')->url($this->video_path) : null;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderByDesc('created_at');
    }

    protected static function newFactory(): NewsArticleFactory
    {
        return NewsArticleFactory::new();
    }
}
