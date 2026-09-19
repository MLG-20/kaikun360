<?php

namespace App\Support\Offers;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;

/**
 * Une offre « Kaikun 360 » est une offre déposée par l'équipe elle-même
 * (super_admin) plutôt que par un prestataire ou un propriétaire.
 *
 * Deux conséquences, portées ici pour ne pas les disperser :
 * - elle est publiée D'EMBLÉE, personne d'autre n'étant là pour la valider ;
 * - le catalogue l'affiche avec l'étiquette « Kaikun 360 ».
 *
 * Aucune colonne dédiée : le déposant EST le signal (`provider_id` /
 * `owner_id` pointe sur un super_admin), ce qui évite un second champ qui
 * pourrait diverger du premier.
 */
class KaikunPublisher
{
    private const CONTAINER_KEY = 'kaikun.super_admin_ids';

    public static function isKaikun(?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        // Une seule requête pour toute une page de catalogue, pas une par ligne.
        // Mémorisé dans le conteneur (et non en statique) : il repart à zéro
        // avec chaque requête, et avec chaque test.
        if (! app()->bound(self::CONTAINER_KEY)) {
            app()->instance(self::CONTAINER_KEY, array_fill_keys(
                User::whereHas('roles', fn ($q) => $q->where('name', UserRole::SUPER_ADMIN->value))
                    ->pluck('id')->all(),
                true,
            ));
        }

        return isset(app(self::CONTAINER_KEY)[$userId]);
    }

    public static function isKaikunUser(User $user): bool
    {
        return $user->hasRole(UserRole::SUPER_ADMIN->value);
    }

    /**
     * Champs de publication directe (statut publié + traçabilité de l'approbation)
     * à fusionner à la création d'une offre déposée par le super_admin.
     *
     * @return array<string, mixed>
     */
    public static function directPublication(User $user, string $publishedStatus, bool $withApprovedAt = false): array
    {
        return [
            'status' => $publishedStatus,
            'approved_by' => $user->id,
            'published_at' => now(),
        ] + ($withApprovedAt ? ['approved_at' => now()] : []);
    }
}
