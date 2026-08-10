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
use Illuminate\Database\Eloquent\SoftDeletes;
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
    use HasFactory, SoftDeletes;

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

    /**
     * Scope : départs qu'un client peut ENCORE prendre (F8.23.a).
     *
     * ⚠️ **Ce filtre n'existait pas, et son absence coûtait de l'argent** : le
     * catalogue public exposait les départs passés, et `POST …/bookings` les
     * acceptait — un client pouvait payer une place sur un car parti trois
     * semaines plus tôt. Le défaut date de B7.2/B7.4 mais était **hors
     * d'atteinte** tant que rien ne pouvait créer un départ : les seules lignes
     * en base venaient du seeder, et personne ne parcourait ce catalogue.
     *
     * ⚠️ **La fiche ne suffisait pas.** L'écran de détail affiche bien « ce
     * départ a déjà eu lieu » depuis F8.10 — mais ce n'est qu'un affichage : il
     * masque le bouton, il ne ferme pas la route. La règle devait vivre côté
     * serveur, là où l'argent se décide.
     *
     * ⚠️ **Une date NULLE reste visible, délibérément** : `departure_at` est
     * nullable pour les services « à la demande » (une navette qu'on affrète, un
     * transfert sans horaire fixe). Ceux-là n'ont pas d'échéance à dépasser ;
     * les exclure retirerait du catalogue des offres parfaitement vivantes.
     */
    public function scopeAVenir(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('departure_at')->orWhere('departure_at', '>=', now());
        });
    }

    protected static function newFactory(): MobilityServiceFactory
    {
        return MobilityServiceFactory::new();
    }
}
