<?php

namespace App\Modules\Pro\Models;

use App\Modules\Pro\Enums\ProviderCategoryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Catégorie de service prestataire — remplace l'ancien enum fermé `ProviderCategory`
 * par une nomenclature EXTENSIBLE : un prestataire peut en proposer une nouvelle,
 * réutilisable par les autres une fois validée par un admin (cf. migration
 * `create_provider_categories_table` et `ProviderCategoryValidator`).
 */
class ProviderCategory extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['key', 'label', 'status', 'created_by_provider_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => ProviderCategoryStatus::class];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'created_by_provider_id');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ProviderCategoryStatus::VALIDE);
    }

    /**
     * Slug stable pour la colonne `key`, dans le même style que les valeurs
     * historiques de l'enum (mots collés, sans accent ni séparateur).
     */
    public static function slugify(string $label): string
    {
        return Str::slug($label, '');
    }
}
