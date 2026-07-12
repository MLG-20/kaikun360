<?php

namespace App\Modules\Mobility\Models;

use App\Models\Booking;
use App\Models\User;
use App\Modules\Mobility\Enums\MobilityServiceStatus;
use App\Modules\Mobility\Enums\MobilityServiceType;
use App\Support\Cache\CatalogCache;
use Database\Factories\MobilityServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Service de mobilité — trajet programmé (module Mobility).
 *
 * Appartient à un prestataire, peut s'appuyer sur un véhicule, n'apparaît dans
 * la recherche qu'une fois publié. Réservable via le modèle transversal Booking.
 */
class MobilityService extends Model
{
    use HasFactory;

    /**
     * Invalide le cache du catalogue des services de mobilité à chaque écriture (B17.2).
     */
    protected static function booted(): void
    {
        $flush = fn () => CatalogCache::flush('mobility');

        static::saved($flush);
        static::deleted($flush);
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'provider_id',
        'vehicle_id',
        'type',
        'departure',
        'destination',
        'departure_at',
        'capacity',
        'price_xof',
        'description',
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
            'type' => MobilityServiceType::class,
            'departure_at' => 'datetime',
            'capacity' => 'integer',
            'price_xof' => 'integer',
            'status' => MobilityServiceStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Les réservations du service (relation polymorphe).
     */
    public function bookings(): MorphMany
    {
        return $this->morphMany(Booking::class, 'bookable');
    }

    /**
     * Scope : services visibles dans la recherche (publiés).
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', MobilityServiceStatus::PUBLIE->value);
    }

    protected static function newFactory(): MobilityServiceFactory
    {
        return MobilityServiceFactory::new();
    }
}
