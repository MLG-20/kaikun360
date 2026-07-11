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
            'submitted_at' => $model->created_at,
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
