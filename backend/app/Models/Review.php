<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Avis polymorphe (modèle transversal, B12.2).
 *
 * `reviewable` = la ressource notée (Vehicle, TourismExperience, Stay…). Un avis
 * n'est déposable que par un utilisateur ayant réellement consommé le service
 * (réservation `terminee` sur cette ressource), et passe par une modération.
 */
class Review extends Model
{
    use HasFactory;

    /**
     * Ressources notables : clé courte (exposée à l'API) → classe Eloquent.
     * Ce sont des ressources réservables (`Booking` polymorphe) : la
     * consommation se prouve par une réservation terminée.
     *
     * @var array<string, class-string>
     */
    public const TYPES = [
        'stay' => \App\Modules\Stay\Models\Stay::class,
        'vehicle' => \App\Modules\Mobility\Models\Vehicle::class,
        'experience' => \App\Modules\Explore\Models\TourismExperience::class,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'user_id',
        'reviewable_type',
        'reviewable_id',
        'rating',
        'comment',
        'status',
        'moderated_by',
        'moderated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => ReviewStatus::class,
            'moderated_at' => 'datetime',
        ];
    }

    /**
     * La ressource notée.
     */
    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * L'auteur de l'avis.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Ne renvoie que les avis publiés (visibles du public).
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ReviewStatus::PUBLIE->value);
    }

    /**
     * Un utilisateur a-t-il consommé la ressource (réservation terminée) ?
     * C'est la condition d'éligibilité pour déposer un avis.
     */
    public static function hasConsumed(User $user, Model $reviewable): bool
    {
        return Booking::query()
            ->where('user_id', $user->id)
            ->where('bookable_type', $reviewable->getMorphClass())
            ->where('bookable_id', $reviewable->getKey())
            ->where('status', BookingStatus::TERMINEE->value)
            ->exists();
    }
}
