<?php

namespace App\Modules\Explore\Models;

use App\Models\Booking;
use App\Models\User;
use App\Modules\Explore\Enums\ExperienceStatus;
use Database\Factories\TourismExperienceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Expérience / circuit touristique (module Explore).
 *
 * Appartient à un prestataire (provider) et n'est visible au catalogue qu'une
 * fois publiée (validée par un agent). Réservable via le modèle transversal
 * Booking (relation polymorphe).
 */
class TourismExperience extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'provider_id',
        'title',
        'destination',
        'description',
        'duration_days',
        'price_xof',
        'capacity',
        'inclusions',
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
            'duration_days' => 'integer',
            'price_xof' => 'integer',
            'capacity' => 'integer',
            'inclusions' => 'array',
            'status' => ExperienceStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * Le prestataire qui propose l'expérience.
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    /**
     * Les réservations de l'expérience (relation polymorphe).
     */
    public function bookings(): MorphMany
    {
        return $this->morphMany(Booking::class, 'bookable');
    }

    /**
     * Scope : expériences visibles au catalogue (publiées).
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ExperienceStatus::PUBLIE->value);
    }

    protected static function newFactory(): TourismExperienceFactory
    {
        return TourismExperienceFactory::new();
    }
}
