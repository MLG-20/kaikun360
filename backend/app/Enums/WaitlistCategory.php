<?php

namespace App\Enums;

/**
 * Catégorie d'un inscrit à la liste d'attente avant ouverture (2026-08-14).
 *
 * Détermine la forme des champs attendus dans `WaitlistEntry::details` — voir
 * `StoreWaitlistEntryRequest::detailRules()`.
 */
enum WaitlistCategory: string
{
    case PROPRIETAIRE = 'proprietaire';
    case PRESTATAIRE = 'prestataire';
    case CLIENT = 'client';
    case TEAM_BUILDING = 'team_building';
    case DIASPORA = 'diaspora';

    /**
     * Libellé lisible (français).
     */
    public function label(): string
    {
        return match ($this) {
            self::PROPRIETAIRE => 'Propriétaire',
            self::PRESTATAIRE => 'Prestataire',
            self::CLIENT => 'Client intéressé',
            self::TEAM_BUILDING => 'Team building',
            self::DIASPORA => 'Diaspora',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
