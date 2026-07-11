<?php

namespace App\Modules\Admin\Validation;

use App\Models\User;
use App\Modules\Explore\Enums\ExperienceStatus;
use App\Modules\Explore\Http\Resources\ExperienceResource;
use App\Modules\Explore\Models\TourismExperience;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Validateur générique des circuits touristiques (module Explore). Reproduit
 * ExperienceValidationController : publication + traçabilité.
 */
class ExperienceValidator implements ResourceValidator
{
    public function type(): string
    {
        return 'experience';
    }

    public function permission(): string
    {
        return 'valider:experience';
    }

    public function pendingQuery(): Builder
    {
        return TourismExperience::query()
            ->where('status', ExperienceStatus::EN_ATTENTE_VALIDATION->value)
            ->oldest();
    }

    public function pendingCount(): int
    {
        return $this->pendingQuery()->count();
    }

    public function find(int|string $id): Model
    {
        return TourismExperience::findOrFail($id);
    }

    public function isPending(Model $model): bool
    {
        return $model->status === ExperienceStatus::EN_ATTENTE_VALIDATION;
    }

    public function toEntry(Model $model): array
    {
        /** @var TourismExperience $model */
        return [
            'type' => $this->type(),
            'id' => $model->id,
            'reference' => $model->reference,
            'label' => $model->title,
            'owner_id' => $model->provider_id,
            'submitted_at' => $model->created_at,
        ];
    }

    public function approve(Model $model, User $actor): array
    {
        /** @var TourismExperience $model */
        $model->update([
            'status' => ExperienceStatus::PUBLIE->value,
            'approved_by' => $actor->id,
            'published_at' => now(),
        ]);

        activity()->causedBy($actor)->performedOn($model)->log('Validation d\'expérience');

        return ['experience' => ExperienceResource::make($model->fresh())];
    }

    public function reject(Model $model, User $actor, ?string $reason): array
    {
        /** @var TourismExperience $model */
        $model->update(['status' => ExperienceStatus::REJETE->value]);

        activity()->causedBy($actor)->performedOn($model)
            ->withProperties(['reason' => $reason])
            ->log('Rejet d\'expérience');

        return ['experience' => ExperienceResource::make($model->fresh())];
    }
}
