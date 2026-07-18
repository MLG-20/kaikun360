<?php

namespace App\Support;

use App\Modules\Explore\Http\Resources\ExperienceResource;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Immo\Http\Resources\PropertyResource;
use App\Modules\Immo\Models\Property;
use App\Modules\Mobility\Http\Resources\MobilityServiceResource;
use App\Modules\Mobility\Http\Resources\VehicleResource;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Stay\Http\Resources\StayResource;
use App\Modules\Stay\Models\Stay;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Registre des types FAVORISABLES (favoris polymorphes, tous univers).
 *
 * Source unique décrivant, pour chaque univers, comment relier un « slug » stable
 * exposé à l'API (property, stay, vehicle, experience, mobility) à :
 *   - son modèle Eloquent et sa ressource JSON (rendu identique au catalogue) ;
 *   - le **scope de visibilité** (seuls les éléments réellement publiés/réservables
 *     peuvent être mis en favori) ;
 *   - les **relations à charger** pour un rendu sans requêtes N+1.
 *
 * Le `favoritable_type` stocké en base est le **nom de classe complet** (même
 * convention que `bookings.bookable_type`) ; les slugs ne servent qu'au contrat
 * d'API (URL, payloads) et à l'affichage frontend.
 */
class Favoritables
{
    /**
     * Table de correspondance slug → configuration.
     *
     * @var array<string, array{model: class-string<Model>, resource: class-string, scope: string, relations: list<string>}>
     */
    private const MAP = [
        'property' => [
            'model' => Property::class,
            'resource' => PropertyResource::class,
            'scope' => 'published',
            'relations' => ['region', 'department', 'commune', 'owner'],
        ],
        'stay' => [
            'model' => Stay::class,
            'resource' => StayResource::class,
            'scope' => 'bookable',
            'relations' => ['property.region', 'property.department', 'property.commune', 'property.owner'],
        ],
        'vehicle' => [
            'model' => Vehicle::class,
            'resource' => VehicleResource::class,
            'scope' => 'published',
            'relations' => [],
        ],
        'experience' => [
            'model' => TourismExperience::class,
            'resource' => ExperienceResource::class,
            'scope' => 'published',
            'relations' => [],
        ],
        'mobility' => [
            'model' => MobilityService::class,
            'resource' => MobilityServiceResource::class,
            'scope' => 'published',
            'relations' => [],
        ],
    ];

    /** Slugs favorisables acceptés (pour la validation des requêtes). */
    public static function slugs(): array
    {
        return array_keys(self::MAP);
    }

    /** Slug d'un type à partir du nom de classe stocké (ex. Property → « property »). */
    public static function slugForClass(string $class): ?string
    {
        foreach (self::MAP as $slug => $config) {
            if ($config['model'] === $class) {
                return $slug;
            }
        }

        return null;
    }

    /** Classe du modèle pour un slug (ou null si inconnu). */
    public static function modelClass(string $slug): ?string
    {
        return self::MAP[$slug]['model'] ?? null;
    }

    /**
     * Retrouve un élément favorisable VISIBLE (publié / réservable) par son slug et
     * son id, ou null s'il n'existe pas / n'est pas visible. On n'autorise jamais
     * la mise en favori d'un élément masqué.
     */
    public static function findVisible(string $slug, int $id): ?Model
    {
        $config = self::MAP[$slug] ?? null;

        if ($config === null) {
            return null;
        }

        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = $config['model']::query()->{$config['scope']}();

        return $query->whereKey($id)->first();
    }

    /** Ressource JSON adaptée au type d'un modèle favorisable donné. */
    public static function resourceFor(Model $model): mixed
    {
        $slug = self::slugForClass($model::class);
        $resourceClass = $slug ? self::MAP[$slug]['resource'] : null;

        return $resourceClass ? $resourceClass::make($model) : null;
    }

    /**
     * Applique l'eager-loading polymorphe à une relation `morphTo` (index des
     * favoris) : chaque type charge ses propres relations d'affichage.
     */
    public static function withRelations(MorphTo $morphTo): MorphTo
    {
        $map = [];

        foreach (self::MAP as $config) {
            $map[$config['model']] = $config['relations'];
        }

        return $morphTo->morphWith($map);
    }
}
