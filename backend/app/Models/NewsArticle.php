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
