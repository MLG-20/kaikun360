<?php

namespace App\Models;

use Database\Factories\VehicleShowcaseCardFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Carte de la section « Location de véhicules » de la page d'accueil.
 *
 * Contrairement aux actualités, il n'y a pas de page de détail par carte :
 * chaque carte pointe directement vers le lien saisi par l'équipe
 * (`link_url`), donc pas de slug ni de route model binding particulier.
 */
class VehicleShowcaseCard extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'category',
        'image_path',
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

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderByDesc('created_at');
    }

    protected static function newFactory(): VehicleShowcaseCardFactory
    {
        return VehicleShowcaseCardFactory::new();
    }
}
