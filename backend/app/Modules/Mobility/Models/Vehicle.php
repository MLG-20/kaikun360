<?php

namespace App\Modules\Mobility\Models;

use App\Models\Booking;
use App\Models\Concerns\HasMedia;
use App\Models\User;
use App\Modules\Mobility\Enums\VehicleStatus;
use App\Modules\Mobility\Enums\VehicleType;
use App\Support\Cache\CatalogCache;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Véhicule / moyen de transport (module Mobility).
 *
 * Appartient à un prestataire, n'apparaît dans la recherche qu'une fois publié
 * (validé par un agent). Réservable via le modèle transversal Booking. La
 * galerie média est branchée via le trait HasMedia (F8.1) : les dépôts étaient
 * déjà acceptés par `POST media/upload`, plus rien ne les relisait.
 */
class Vehicle extends Model
{
    use HasFactory;
    use HasMedia;

    /**
     * Invalide le cache du catalogue des véhicules à chaque écriture (B17.2).
     */
    protected static function booted(): void
    {
        $flush = fn () => CatalogCache::flush('vehicles');

        static::saved($flush);
        static::deleted($flush);
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'provider_id',
        'type',
        'brand',
        'model',
        'capacity',
        'price_per_day_xof',
        'has_driver',
        'caution_xof',
        'description',
        'insurance_ref',
        'driver_identity',
        'life_jackets_count',
        'weather_compliant',
        'provider_compliant',
        'status',
        'published_at',
        'approved_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VehicleType::class,
            'capacity' => 'integer',
            'price_per_day_xof' => 'integer',
            'has_driver' => 'boolean',
            'caution_xof' => 'integer',
            'life_jackets_count' => 'integer',
            'weather_compliant' => 'boolean',
            'provider_compliant' => 'boolean',
            'status' => VehicleStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * Le prestataire propriétaire.
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    /**
     * Les réservations du véhicule (relation polymorphe).
     */
    public function bookings(): MorphMany
    {
        return $this->morphMany(Booking::class, 'bookable');
    }

    /**
     * Scope : véhicules visibles dans la recherche (publiés).
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', VehicleStatus::PUBLIE->value);
    }

    protected static function newFactory(): VehicleFactory
    {
        return VehicleFactory::new();
    }
}
