<?php

namespace App\Modules\Admin\Validation;

use App\Models\User;
use App\Modules\Mobility\Enums\VehicleStatus;
use App\Modules\Mobility\Events\VehicleValidated;
use App\Modules\Mobility\Http\Resources\VehicleResource;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Mobility\Services\VehicleComplianceChecker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Validateur générique des véhicules (module Mobility). Comme
 * VehicleValidationController, la validation est BLOQUÉE (422) tant que la
 * conformité obligatoire n'est pas complète.
 */
class VehicleValidator implements ResourceValidator
{
    public function __construct(private readonly VehicleComplianceChecker $checker)
    {
    }

    public function type(): string
    {
        return 'vehicle';
    }

    public function permission(): string
    {
        return 'valider:vehicule';
    }

    public function pendingQuery(): Builder
    {
        return Vehicle::query()
            // Évite le N+1 : déposant ET galerie sont affichés dans la file.
            ->with(['provider', 'allMedia'])
            ->where('status', VehicleStatus::EN_ATTENTE_VALIDATION->value)
            ->oldest();
    }

    public function pendingCount(): int
    {
        return $this->pendingQuery()->count();
    }

    public function find(int|string $id): Model
    {
        return Vehicle::findOrFail($id);
    }

    public function isPending(Model $model): bool
    {
        return $model->status === VehicleStatus::EN_ATTENTE_VALIDATION;
    }

    public function toEntry(Model $model): array
    {
        /** @var Vehicle $model */
        return [
            'type' => $this->type(),
            'id' => $model->id,
            'reference' => $model->reference,
            'label' => trim($model->brand.' '.$model->model),
            'owner_id' => $model->provider_id,
            'owner' => OwnerEntry::from($model->provider),
            'submitted_at' => $model->created_at,
            // F8.1 — l'agent doit VOIR ce qu'il publie avant de trancher.
            'media' => MediaEntry::summary($model),
        ];
    }

    public function toDetail(Model $model): array
    {
        /** @var Vehicle $model */
        $model->loadMissing(['provider', 'allMedia']);

        return [
            ...$this->toEntry($model),
            // Galerie ENTIÈRE (pas l'aperçu de la file) : c'est ici que l'agent
            // examine chaque photo avant de publier sur le site vitrine.
            'media' => MediaEntry::summary($model, null),
            'fields' => [
                'Type' => $model->type?->label() ?? $model->type,
                'Prix par jour' => $model->price_per_day_xof,
                'Caution' => $model->caution_xof,
                'Capacité' => $model->capacity,
                'Avec chauffeur' => $model->has_driver ? 'Oui' : 'Non',
                'Description' => $model->description,
                // Conformité : ces champs conditionnent l'approbation côté
                // métier (VehicleComplianceChecker), l'agent doit les voir.
                'Assurance' => $model->insurance_ref,
                'Identité du chauffeur' => $model->driver_identity,
                'Gilets de sauvetage' => $model->life_jackets_count,
            ],
        ];
    }

    public function approve(Model $model, User $actor): array
    {
        /** @var Vehicle $model */
        $missing = $this->checker->missingFields($model);
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'compliance' => ['Conformité incomplète, champs manquants : '.implode(', ', $missing).'.'],
            ]);
        }

        $model->update([
            'status' => VehicleStatus::PUBLIE->value,
            'approved_by' => $actor->id,
            'published_at' => now(),
        ]);

        activity()->causedBy($actor)->performedOn($model)->log('Validation de véhicule');

        VehicleValidated::dispatch($model);

        return ['vehicle' => VehicleResource::make($model->fresh())];
    }

    public function reject(Model $model, User $actor, ?string $reason): array
    {
        /** @var Vehicle $model */
        $model->update(['status' => VehicleStatus::REJETE->value]);

        activity()->causedBy($actor)->performedOn($model)
            ->withProperties(['reason' => $reason])
            ->log('Rejet de véhicule');

        return ['vehicle' => VehicleResource::make($model->fresh())];
    }
}
