<?php

namespace App\Modules\Admin\Validation;

use App\Models\User;
use App\Modules\Mobility\Enums\MobilityServiceStatus;
use App\Modules\Mobility\Http\Resources\MobilityServiceResource;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Services\VehicleComplianceChecker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Validateur des DÉPARTS PROGRAMMÉS dans la file du back-office (F8.23).
 *
 * ⚠️ Enregistré sous la clé `mobility_service`, distincte de `vehicle` : la file
 * de validation devait pouvoir les compter et les trancher séparément, un agent
 * ne valide pas un minibus et une navette du lundi matin avec les mêmes yeux.
 *
 * Deux règles de refus lui sont propres, et aucune n'existe pour un véhicule :
 * un départ dont la date est passée, et un départ opéré par un véhicule qui
 * n'est pas lui-même publié.
 */
class MobilityServiceValidator implements ResourceValidator
{
    public function __construct(private readonly VehicleComplianceChecker $checker) {}

    public function type(): string
    {
        return 'mobility_service';
    }

    /**
     * ⚠️ **Pas de permission neuve** : `valider:vehicule` est la permission de
     * la mobilité. En introduire une seconde imposerait de ré-attribuer des
     * droits à toute l'équipe pour un geste que les mêmes agents font déjà.
     */
    public function permission(): string
    {
        return 'valider:vehicule';
    }

    public function pendingQuery(): Builder
    {
        return MobilityService::query()
            // Évite le N+1 : déposant et véhicule illustrant le départ sont
            // affichés dans la file.
            ->with(['provider', 'vehicle.allMedia'])
            ->where('status', MobilityServiceStatus::EN_ATTENTE_VALIDATION->value)
            // ⚠️ Le plus PROCHE en premier, et non le plus ancien déposé (seul
            // cas de la file à ne pas trier par `oldest()`) : un départ a une
            // date de péremption. Celui qui part demain doit être tranché
            // aujourd'hui, même s'il a été déposé après celui de septembre.
            ->orderBy('departure_at');
    }

    public function pendingCount(): int
    {
        return $this->pendingQuery()->count();
    }

    public function find(int|string $id): Model
    {
        return MobilityService::findOrFail($id);
    }

    public function isPending(Model $model): bool
    {
        return $model->status === MobilityServiceStatus::EN_ATTENTE_VALIDATION;
    }

    public function toEntry(Model $model): array
    {
        /** @var MobilityService $model */
        return [
            'type' => $this->type(),
            'id' => $model->id,
            'reference' => $model->reference,
            'label' => $model->departure.' → '.$model->destination,
            'owner_id' => $model->provider_id,
            'owner' => OwnerEntry::from($model->provider),
            'submitted_at' => $model->created_at,
            // Un départ n'a pas de galerie propre : il hérite de celle de son
            // véhicule (F8.18). L'agent voit donc ce que verra le client.
            'media' => $model->vehicle ? MediaEntry::summary($model->vehicle) : [],
        ];
    }

    public function toDetail(Model $model): array
    {
        /** @var MobilityService $model */
        $model->loadMissing(['provider', 'vehicle.allMedia']);

        return [
            ...$this->toEntry($model),
            'media' => $model->vehicle ? MediaEntry::summary($model->vehicle, null) : [],
            'fields' => [
                'Type' => $model->type?->label() ?? $model->type,
                'Départ' => $model->departure,
                'Destination' => $model->destination,
                'Date de départ' => $model->departure_at?->format('d/m/Y à H:i'),
                'Places mises en vente' => $model->capacity,
                'Prix par place' => $model->price_xof,
                'Description' => $model->description,
                // Le véhicule conditionne l'approbation : l'agent doit voir
                // lequel opère, et s'il est lui-même en règle.
                'Véhicule' => $this->libelleDuVehicule($model),
                'Capacité du véhicule' => $model->vehicle?->capacity,
            ],
        ];
    }

    /**
     * Valide et publie un départ.
     *
     * ⚠️ **Deux refus propres au départ, absents du contrôle d'un véhicule.**
     */
    public function approve(Model $model, User $actor): array
    {
        /** @var MobilityService $model */

        // (1) Publier un départ déjà passé ne créerait qu'une ligne morte au
        // catalogue : la fiche affiche « ce départ a déjà eu lieu » et la
        // réservation est impossible. L'agent doit refuser, pas publier.
        if ($model->departure_at !== null && $model->departure_at->isPast()) {
            throw ValidationException::withMessages([
                'departure_at' => ['Ce départ est déjà passé : il ne peut plus être publié. '
                    .'Refusez-le, ou demandez au prestataire de le reprogrammer.'],
            ]);
        }

        // (2) Un départ hérite des photos ET de la conformité de son véhicule.
        // Le publier alors que le véhicule ne l'est pas ferait entrer par la
        // bande, dans le catalogue public, un véhicule dont l'assurance ou les
        // gilets n'ont jamais été contrôlés — exactement ce que le CDC §12
        // interdit pour le transport.
        if ($model->vehicle !== null) {
            $manquants = $this->checker->missingFields($model->vehicle);

            if ($manquants !== []) {
                throw ValidationException::withMessages([
                    'compliance' => ['Le véhicule qui opère ce départ n\'est pas conforme, '
                        .'champs manquants : '.implode(', ', $manquants).'.'],
                ]);
            }
        }

        $model->update([
            'status' => MobilityServiceStatus::PUBLIE->value,
            'approved_by' => $actor->id,
            'published_at' => now(),
        ]);

        activity()->causedBy($actor)->performedOn($model)->log('Validation de départ programmé');

        return ['mobility_service' => MobilityServiceResource::make($model->fresh())];
    }

    public function reject(Model $model, User $actor, ?string $reason): array
    {
        /** @var MobilityService $model */
        $model->update(['status' => MobilityServiceStatus::REJETE->value]);

        activity()->causedBy($actor)->performedOn($model)
            ->withProperties(['reason' => $reason])
            ->log('Rejet de départ programmé');

        return ['mobility_service' => MobilityServiceResource::make($model->fresh())];
    }

    /**
     * Le véhicule affecté, en clair — ou l'absence, dite explicitement.
     *
     * ⚠️ Un départ **sans véhicule** est permis (`vehicle_id` est nullable) mais
     * il arrive nu au catalogue : ni photo, ni conformité contrôlée. L'agent
     * doit le lire dans le dossier, pas le déduire d'une ligne vide.
     */
    private function libelleDuVehicule(MobilityService $model): string
    {
        $vehicle = $model->vehicle;

        if ($vehicle === null) {
            return 'Aucun véhicule rattaché — le départ sera publié sans photo '
                .'et sans contrôle de conformité du véhicule.';
        }

        $identite = trim($vehicle->brand.' '.$vehicle->model);

        return trim(($identite !== '' ? $identite : $vehicle->type?->label() ?? 'Véhicule')
            .' ('.$vehicle->reference.') — '.($vehicle->status?->label() ?? ''));
    }
}
