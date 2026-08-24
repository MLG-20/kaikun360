<?php

namespace App\Modules\Admin\Validation;

use App\Models\User;
use App\Modules\Pro\Enums\ProviderCategoryStatus;
use App\Modules\Pro\Http\Resources\ProviderCategoryResource;
use App\Modules\Pro\Models\ProviderCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Validateur des catégories de service PROPOSÉES par un prestataire (F5).
 *
 * Approuver rend la catégorie assignable par tous les prestataires ; refuser la
 * laisse en base (elle reste utilisable par son auteur, cf.
 * `AssignableProviderCategory`) mais ne la publie jamais dans le sélecteur
 * partagé.
 */
class ProviderCategoryValidator implements ResourceValidator
{
    public function type(): string
    {
        return 'provider_category';
    }

    public function permission(): string
    {
        return 'valider:categorie-prestataire';
    }

    public function pendingQuery(): Builder
    {
        return ProviderCategory::query()
            ->with('createdBy.user') // évite le N+1 : le déposant est affiché dans la file.
            ->where('status', ProviderCategoryStatus::EN_ATTENTE->value)
            ->oldest();
    }

    public function pendingCount(): int
    {
        return $this->pendingQuery()->count();
    }

    public function find(int|string $id): Model
    {
        return ProviderCategory::findOrFail($id);
    }

    public function isPending(Model $model): bool
    {
        return $model->status === ProviderCategoryStatus::EN_ATTENTE;
    }

    public function toEntry(Model $model): array
    {
        /** @var ProviderCategory $model */
        return [
            'type' => $this->type(),
            'id' => $model->id,
            'reference' => null,
            'label' => $model->label,
            'owner_id' => $model->created_by_provider_id,
            'owner' => OwnerEntry::from($model->createdBy?->user),
            'submitted_at' => $model->created_at,
            // Une catégorie n'est pas une ressource illustrable : galerie
            // toujours vide, mais la clé reste présente pour que la file ait la
            // même forme d'un onglet à l'autre.
            'media' => MediaEntry::summary($model),
        ];
    }

    public function toDetail(Model $model): array
    {
        /** @var ProviderCategory $model */
        $model->loadMissing('createdBy.user');

        return [
            ...$this->toEntry($model),
            'fields' => [
                'Clé' => $model->key,
                'Proposée par' => $model->createdBy?->business_name,
            ],
        ];
    }

    public function approve(Model $model, User $actor): array
    {
        /** @var ProviderCategory $model */
        $model->update(['status' => ProviderCategoryStatus::VALIDE]);

        activity()->causedBy($actor)->performedOn($model)->log('Validation de catégorie prestataire');

        return ['provider_category' => ProviderCategoryResource::make($model->fresh())];
    }

    public function reject(Model $model, User $actor, ?string $reason): array
    {
        /** @var ProviderCategory $model */
        $model->update(['status' => ProviderCategoryStatus::REFUSE]);

        activity()->causedBy($actor)->performedOn($model)
            ->withProperties(['reason' => $reason])
            ->log('Refus de catégorie prestataire');

        return ['provider_category' => ProviderCategoryResource::make($model->fresh())];
    }
}
