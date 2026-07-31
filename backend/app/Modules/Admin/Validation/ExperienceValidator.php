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
            // Évite le N+1 : déposant ET galerie sont affichés dans la file.
            ->with(['provider', 'allMedia'])
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
            'owner' => OwnerEntry::from($model->provider),
            'submitted_at' => $model->created_at,
            // F8.1 — l'agent doit VOIR ce qu'il publie avant de trancher.
            'media' => MediaEntry::summary($model),
        ];
    }

    public function toDetail(Model $model): array
    {
        /** @var TourismExperience $model */
        $model->loadMissing(['provider', 'allMedia']);

        return [
            ...$this->toEntry($model),
            // Galerie ENTIÈRE (pas l'aperçu de la file) : c'est ici que l'agent
            // examine chaque photo avant de publier sur le site vitrine.
            'media' => MediaEntry::summary($model, null),
            'fields' => [
                'Destination' => $model->destination,
                'Durée (jours)' => $model->duration_days,
                'Prix' => $model->price_xof,
                'Capacité' => $model->capacity,
                'Description' => $model->description,
                // `inclusions` est casté en tableau : on l'aplatit pour l'affichage.
                'Inclusions' => is_array($model->inclusions)
                    ? implode(' · ', $model->inclusions)
                    : $model->inclusions,
            ],
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
