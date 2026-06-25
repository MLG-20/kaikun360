<?php

namespace App\Modules\Immo\Enums;

/**
 * Statuts du cycle de vie d'un bien immobilier (table `properties`).
 *
 * Valeur par défaut à la création : EN_ATTENTE_VALIDATION
 * (un bien n'est jamais visible publiquement tant qu'un agent ne l'a pas validé —
 *  cf. cahier des charges, phases B2 et B15).
 */
enum PropertyStatus: string
{
    case EN_ATTENTE_VALIDATION = 'en_attente_validation';
    case PUBLIE = 'publie';
    case SUSPENDU = 'suspendu';
    case ARCHIVE = 'archive';
    case REJETE = 'rejete';

    /**
     * Libellé lisible (français) pour l'affichage et le back-office.
     */
    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE_VALIDATION => 'En attente de validation',
            self::PUBLIE => 'Publié',
            self::SUSPENDU => 'Suspendu',
            self::ARCHIVE => 'Archivé',
            self::REJETE => 'Rejeté',
        };
    }

    /**
     * Liste des valeurs brutes — pratique pour les règles de validation
     * (ex. Rule::in(PropertyStatus::values())).
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
