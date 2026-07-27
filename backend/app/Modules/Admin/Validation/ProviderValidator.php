<?php

namespace App\Modules\Admin\Validation;

use App\Models\User;
use App\Modules\Pro\Enums\ProviderStatus;
use App\Modules\Pro\Http\Resources\ProviderResource;
use App\Modules\Pro\Models\Provider;
use App\Modules\Pro\Services\ProviderValidationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Validateur générique des prestataires (module Pro). Délègue au
 * ProviderValidationService : valider un prestataire synchronise son profil et
 * débloque sa publication ; le refuser la bloque.
 */
class ProviderValidator implements ResourceValidator
{
    public function __construct(private readonly ProviderValidationService $validation)
    {
    }

    public function type(): string
    {
        return 'provider';
    }

    public function permission(): string
    {
        return 'valider:prestataire';
    }

    public function pendingQuery(): Builder
    {
        return Provider::query()
            ->with('user') // évite le N+1 : le titulaire du compte est affiché dans la file.
            ->where('status', ProviderStatus::EN_ATTENTE->value)
            ->oldest();
    }

    public function pendingCount(): int
    {
        return $this->pendingQuery()->count();
    }

    public function find(int|string $id): Model
    {
        return Provider::findOrFail($id);
    }

    public function isPending(Model $model): bool
    {
        return $model->status === ProviderStatus::EN_ATTENTE;
    }

    public function toEntry(Model $model): array
    {
        /** @var Provider $model */
        return [
            'type' => $this->type(),
            'id' => $model->id,
            'reference' => null,
            'label' => $model->business_name,
            'owner_id' => $model->user_id,
            'owner' => OwnerEntry::from($model->user),
            'submitted_at' => $model->created_at,
        ];
    }

    public function approve(Model $model, User $actor): array
    {
        /** @var Provider $model */
        $provider = $this->validation->validate($model, $actor);

        activity()->causedBy($actor)->performedOn($provider)->log('Validation de prestataire');

        return ['provider' => ProviderResource::make($provider)];
    }

    public function reject(Model $model, User $actor, ?string $reason): array
    {
        /** @var Provider $model */
        $provider = $this->validation->reject($model);

        activity()->causedBy($actor)->performedOn($provider)
            ->withProperties(['reason' => $reason])
            ->log('Refus de prestataire');

        return ['provider' => ProviderResource::make($provider)];
    }
}
