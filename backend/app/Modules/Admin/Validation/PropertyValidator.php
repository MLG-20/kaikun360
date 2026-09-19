<?php

namespace App\Modules\Admin\Validation;

use App\Models\User;
use App\Modules\Immo\Enums\PropertyStatus;
use App\Modules\Immo\Events\PropertyValidated;
use App\Modules\Immo\Http\Resources\PropertyResource;
use App\Modules\Immo\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Validateur générique des biens (module Immo). Reproduit fidèlement les effets
 * de bord de PropertyValidationController : publication, traçabilité, événement
 * PropertyValidated (qui notifie le propriétaire).
 */
class PropertyValidator implements ResourceValidator
{
    public function type(): string
    {
        return 'property';
    }

    public function permission(): string
    {
        return 'valider:bien';
    }

    public function pendingQuery(): Builder
    {
        return Property::query()
            // Évite le N+1 : déposant ET galerie sont affichés dans la file.
            ->with(['owner', 'allMedia'])
            ->where('status', PropertyStatus::EN_ATTENTE_VALIDATION->value)
            ->oldest();
    }

    public function pendingCount(): int
    {
        return $this->pendingQuery()->count();
    }

    public function find(int|string $id): Model
    {
        return Property::findOrFail($id);
    }

    public function isPending(Model $model): bool
    {
        return $model->status === PropertyStatus::EN_ATTENTE_VALIDATION;
    }

    public function toEntry(Model $model): array
    {
        /** @var Property $model */
        return [
            'type' => $this->type(),
            'id' => $model->id,
            'reference' => null,
            'label' => $model->title,
            'owner_id' => $model->owner_id,
            'owner' => OwnerEntry::from($model->owner),
            'submitted_at' => $model->created_at,
            // F8.1 — l'agent doit VOIR ce qu'il publie avant de trancher.
            'media' => MediaEntry::summary($model),
        ];
    }

    public function toDetail(Model $model): array
    {
        /** @var Property $model */
        $model->loadMissing(['owner', 'allMedia', 'region', 'department', 'commune', 'stay']);
        $stay = $model->stay;

        return [
            ...$this->toEntry($model),
            // Galerie ENTIÈRE (pas l'aperçu de la file) : c'est ici que l'agent
            // examine chaque photo avant de publier sur le site vitrine.
            'media' => MediaEntry::summary($model, null),
            'fields' => [
                'Type' => $model->type?->label() ?? $model->type,
                'Prix' => $model->price_xof,
                'Caution mensuelle' => $model->caution_xof,
                'Caution (mois)' => $model->caution_months,
                'Caution totale' => $model->caution_total_xof,
                'Description' => $model->description,
                'Région' => $model->region?->name,
                'Département' => $model->department?->name,
                'Commune' => $model->commune?->name,
                'Adresse' => $model->address,
                'Zone touristique' => $model->tourist_zone ? 'Oui' : 'Non',
                'Lien Google Maps' => $model->maps_link,
                // Mode de location : sans lui, un bien uniquement loué à la
                // nuitée montrait un dossier sans aucun prix.
                'Mode de location' => match (true) {
                    $stay !== null && $model->price_xof => 'Mensuelle et nuitées',
                    $stay !== null => 'Nuitées',
                    default => 'Mensuelle / vente',
                },
                // Configuration nuitées (Stay) : tout ce que le formulaire demande.
                'Prix par nuit' => $stay?->price_per_night_xof,
                'Voyageurs (capacité)' => $stay?->capacity,
                'Séjour minimum (nuits)' => $stay?->min_nights,
                'Séjour maximum (nuits)' => $stay?->max_nights,
                'Arrivée à partir de' => $stay?->check_in_time ? substr((string) $stay->check_in_time, 0, 5) : null,
                'Départ avant' => $stay?->check_out_time ? substr((string) $stay->check_out_time, 0, 5) : null,
                'Équipements' => self::asText($stay?->amenities),
                'Règles du séjour' => self::asText($stay?->rules),
                'Nuitées actives' => $stay === null ? null : ($stay->is_active ? 'Oui' : 'Non'),
            ],
        ];
    }

    /** @param  array<int|string, mixed>|null  $value */
    private static function asText(?array $value): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }

        return implode(' · ', array_map(
            fn ($item) => is_scalar($item) ? (string) $item : json_encode($item, JSON_UNESCAPED_UNICODE),
            array_values($value),
        ));
    }

    public function approve(Model $model, User $actor): array
    {
        /** @var Property $model */
        $model->update([
            'status' => PropertyStatus::PUBLIE->value,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'published_at' => now(),
        ]);

        activity()->causedBy($actor)->performedOn($model)->log('Validation de bien');

        PropertyValidated::dispatch($model);

        return ['property' => PropertyResource::make(
            $model->fresh()->load(['region', 'department', 'commune', 'owner'])
        )];
    }

    public function reject(Model $model, User $actor, ?string $reason): array
    {
        /** @var Property $model */
        $model->update(['status' => PropertyStatus::REJETE->value]);

        activity()->causedBy($actor)->performedOn($model)
            ->withProperties(['reason' => $reason])
            ->log('Rejet de bien');

        return ['property' => PropertyResource::make(
            $model->fresh()->load(['region', 'department', 'commune', 'owner'])
        )];
    }
}
