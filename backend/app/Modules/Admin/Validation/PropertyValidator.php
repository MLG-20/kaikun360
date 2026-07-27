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
            ->with('owner') // évite le N+1 : le déposant est affiché dans la file.
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
        ];
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
