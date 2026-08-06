<?php

namespace App\Modules\Mobility\Enums;

/**
 * Statuts du cycle de vie d'un véhicule (table `vehicles`).
 *
 * Comme les biens et les expériences, un véhicule n'apparaît dans la recherche
 * qu'une fois validé (défaut EN_ATTENTE_VALIDATION).
 */
enum VehicleStatus: string
{
    case EN_ATTENTE_VALIDATION = 'en_attente_validation';
    case PUBLIE = 'publie';
    case SUSPENDU = 'suspendu';
    case REJETE = 'rejete';

    /**
     * **Retiré par son prestataire** (F8.19) — retrait VOLONTAIRE, à ne jamais
     * confondre avec `SUSPENDU`, qui est une sanction de l'équipe Kaikun.
     *
     * ⚠️ Ce statut existe parce qu'une offre déjà réservée **ne peut pas être
     * supprimée** : les réservations pointent dessus par une relation
     * polymorphe, sans clé étrangère pour les retenir. La supprimer laisserait
     * des clients avec une réservation dont l'objet a disparu de leur
     * historique. Retirée, l'offre quitte le catalogue et reste lisible.
     */
    case RETIRE = 'retire';

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE_VALIDATION => 'En attente de validation',
            self::PUBLIE => 'Publié',
            self::SUSPENDU => 'Suspendu',
            self::REJETE => 'Rejeté',
            self::RETIRE => 'Retiré du catalogue',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
