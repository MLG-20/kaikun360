<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\ReviewStatus;
use App\Modules\Pro\Enums\MissionStatus;
use App\Modules\Pro\Models\Provider;
use App\Modules\Pro\Models\ProviderMission;
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
     *
     * Deux familles cohabitent :
     *   - les ressources **réservables** (`Booking` polymorphe : nuitée, véhicule,
     *     expérience) — la consommation se prouve par une réservation terminée ;
     *   - le **prestataire lui-même** (`provider`, F5.5) — noté directement par le
     *     client d'une **mission terminée** (`ProviderMission`), pour les
     *     prestations qui ne passent pas par une réservation catalogue.
     *
     * La preuve d'éligibilité diffère donc selon la cible (cf. `ReviewPolicy`).
     *
     * @var array<string, class-string>
     */
    public const TYPES = [
        'stay' => \App\Modules\Stay\Models\Stay::class,
        'vehicle' => \App\Modules\Mobility\Models\Vehicle::class,
        'experience' => \App\Modules\Explore\Models\TourismExperience::class,
        'provider' => \App\Modules\Pro\Models\Provider::class,
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
     * C'est la condition d'éligibilité pour déposer un avis sur une ressource
     * réservable (nuitée, véhicule, expérience).
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

    /**
     * Un utilisateur a-t-il fait réaliser une mission **terminée** par ce
     * prestataire ? C'est la condition d'éligibilité pour noter le prestataire
     * directement (F5.5) : les prestations sur mission ne passent pas par une
     * réservation catalogue, la preuve de consommation est donc la mission.
     */
    public static function hasCompletedMissionWith(User $user, Provider $provider): bool
    {
        return ProviderMission::query()
            ->where('provider_id', $provider->id)
            ->where('client_id', $user->id)
            ->where('status', MissionStatus::TERMINEE->value)
            ->exists();
    }
}
