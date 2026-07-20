<?php

namespace App\Modules\Immo\Models;

use App\Models\Commune;
use App\Models\Department;
use App\Models\Region;
use App\Models\User;
use App\Modules\Immo\Enums\PropertyStatus;
use App\Modules\Immo\Enums\PropertyType;
use App\Support\Cache\CatalogCache;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Bien immobilier (module Immo).
 *
 * Appartient à un propriétaire (User) et possède des documents.
 * N'est visible publiquement que lorsqu'il est au statut PUBLIE.
 */
class Property extends Model
{
    use HasFactory;

    /**
     * Invalide le cache des catalogues à chaque écriture (B17.2). Le catalogue
     * des nuitées est aussi invalidé car chaque nuitée embarque son bien et sa
     * visibilité dépend de la publication de ce bien.
     */
    protected static function booted(): void
    {
        $flush = function (): void {
            CatalogCache::flush('properties');
            CatalogCache::flush('stays');
        };

        static::saved($flush);
        static::deleted($flush);
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'type',
        'title',
        'description',
        'price_xof',
        'region_id',
        'department_id',
        'commune_id',
        'tourist_zone',
        'address',
        'latitude',
        'longitude',
        'status',
        'verification_level',
        'approved_by',
        'approved_at',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PropertyType::class,
            'status' => PropertyStatus::class,
            'price_xof' => 'integer',
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Le propriétaire du bien.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Les pièces justificatives du bien.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(PropertyDocument::class);
    }

    /**
     * Configuration « nuitées » du bien (relation 1–1, module Stay).
     *
     * Présente uniquement si le propriétaire propose le bien en location courte
     * durée. Un bien loué au mois seulement n'a pas de Stay ; un bien « mixte »
     * a à la fois un `price_xof` (loyer mensuel) et une config `stay` active.
     */
    public function stay(): HasOne
    {
        return $this->hasOne(\App\Modules\Stay\Models\Stay::class);
    }

    /** Région (référentiel géographique). */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** Département (référentiel géographique). */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** Commune (référentiel géographique). */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /**
     * Scope : uniquement les biens publiés (visibles au public).
     * Centralise la règle "le catalogue public ne montre que les biens validés".
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PropertyStatus::PUBLIE->value);
    }

    protected static function newFactory(): PropertyFactory
    {
        return PropertyFactory::new();
    }
}
