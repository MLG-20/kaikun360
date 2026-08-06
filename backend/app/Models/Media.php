<?php

namespace App\Models;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * Média polymorphe (modèle transversal, B12.1).
 *
 * `mediable` = la ressource illustrée (Property, Vehicle, TourismExperience…).
 * Une image est stockée sur un disque (`disk` + `path`) ; une vidéo pointe une
 * URL externe (`url`). Un seul média `is_primary` par ressource (image de une).
 */
class Media extends Model
{
    use HasFactory;

    /**
     * Ressources autorisées à porter des médias : clé courte (exposée à l'API)
     * → classe Eloquent. On n'accepte jamais une classe arbitraire du client, et
     * chacune de ces ressources possède une policy `update` (réutilisée pour
     * l'autorisation de dépôt/suppression).
     *
     * @var array<string, class-string>
     */
    public const TYPES = [
        'property' => \App\Modules\Immo\Models\Property::class,
        'vehicle' => \App\Modules\Mobility\Models\Vehicle::class,
        'experience' => \App\Modules\Explore\Models\TourismExperience::class,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'mediable_type',
        'mediable_id',
        'uploaded_by',
        'type',
        'disk',
        'path',
        'url',
        'original_name',
        'mime_type',
        'size_bytes',
        'is_primary',
        'position',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'status' => MediaStatus::class,
            'is_primary' => 'boolean',
            'position' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    /**
     * Catalogues publics à régénérer quand un média de ce type change (F8.18).
     *
     * ⚠️ **Un véhicule touche DEUX catalogues** : le sien et celui des trajets,
     * dont les cartes sont illustrées par le véhicule qui les opère. Un bien en
     * touche deux pour la même raison (les nuitées). Oublier le second laisserait
     * une moitié du site afficher la vignette de repli après un dépôt de photo.
     *
     * @var array<class-string, list<string>>
     */
    private const CATALOGS_TO_FLUSH = [
        \App\Modules\Immo\Models\Property::class => ['properties', 'stays'],
        \App\Modules\Mobility\Models\Vehicle::class => ['vehicles', 'mobility'],
        \App\Modules\Explore\Models\TourismExperience::class => ['experiences'],
    ];

    /**
     * Un média qui change régénère le catalogue de sa ressource (F8.18).
     *
     * POURQUOI : les listes publiques sont mises en cache 5 minutes
     * (`CatalogCache`), et ce cache n'est vidé que par la sauvegarde de la
     * ressource elle-même. Or déposer une photo **ne touche pas** le véhicule ni
     * le bien : sans ce crochet, le partenaire téléverse, retourne au catalogue,
     * et n'y voit rien changer pendant cinq minutes. Il en conclut que le dépôt a
     * échoué et recommence — c'est le genre de silence qui fait abandonner.
     *
     * Couvre le dépôt, la suppression, le changement de couverture **et** la
     * modération (un média masqué doit disparaître des cartes tout de suite).
     */
    protected static function booted(): void
    {
        $flush = function (self $media) {
            foreach (self::CATALOGS_TO_FLUSH[$media->mediable_type] ?? [] as $catalog) {
                \App\Support\Cache\CatalogCache::flush($catalog);
            }
        };

        static::saved($flush);
        static::deleted($flush);
    }

    /**
     * La ressource illustrée par ce média.
     */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * L'auteur du dépôt.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * URL publique du média : URL externe (vidéo) ou URL du fichier stocké.
     */
    public function resolveUrl(): ?string
    {
        if ($this->url) {
            return $this->url;
        }

        return $this->path ? Storage::disk($this->disk)->url($this->path) : null;
    }

    /**
     * Ne renvoie que les médias visibles (non masqués).
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', MediaStatus::ACTIF->value);
    }
}
