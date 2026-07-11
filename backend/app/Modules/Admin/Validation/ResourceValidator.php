<?php

namespace App\Modules\Admin\Validation;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Contrat d'un validateur de ressource pour le back-office générique (B13.2).
 *
 * Chaque ressource soumise à validation par un agent (bien, véhicule, circuit,
 * prestataire) fournit une implémentation qui encapsule :
 *   - sa clé publique (`type`) et la permission requise ;
 *   - la requête des éléments EN ATTENTE (file de validation) ;
 *   - la normalisation d'un élément en entrée de file ;
 *   - les actions `approve` / `reject`, qui RÉUTILISENT la logique métier du
 *     module (événements, services de conformité, etc.) — aucune règle n'est
 *     réécrite ici, elle est seulement centralisée derrière une façade commune.
 *
 * Ce contrat permet à un unique endpoint générique
 * (`PATCH /admin/validate/{type}/{id}`) de piloter n'importe quel type, tout en
 * conservant les effets de bord propres à chaque module.
 */
interface ResourceValidator
{
    /**
     * Clé publique du type, telle qu'utilisée dans l'URL (ex. `property`).
     */
    public function type(): string;

    /**
     * Permission Spatie exigée pour valider/refuser ce type (ex. `valider:bien`).
     */
    public function permission(): string;

    /**
     * Requête ordonnée des éléments EN ATTENTE de validation.
     */
    public function pendingQuery(): Builder;

    /**
     * Nombre d'éléments en attente (compteur de file).
     */
    public function pendingCount(): int;

    /**
     * Retrouve un élément par son identifiant, ou lève une 404.
     */
    public function find(int|string $id): Model;

    /**
     * Indique si l'élément est encore EN ATTENTE (donc actionnable). Un élément
     * déjà validé ou refusé n'est plus modérable → 422 côté contrôleur.
     */
    public function isPending(Model $model): bool;

    /**
     * Représentation normalisée d'un élément dans la file de validation.
     *
     * @return array<string, mixed>
     */
    public function toEntry(Model $model): array;

    /**
     * Valide (publie) l'élément. Peut lever une ValidationException (ex. véhicule
     * dont la conformité est incomplète).
     *
     * @return array<string, mixed> Charge utile de la ressource après validation.
     */
    public function approve(Model $model, User $actor): array;

    /**
     * Refuse l'élément, avec un motif facultatif tracé dans le journal d'activité.
     *
     * @return array<string, mixed> Charge utile de la ressource après refus.
     */
    public function reject(Model $model, User $actor, ?string $reason): array;
}
