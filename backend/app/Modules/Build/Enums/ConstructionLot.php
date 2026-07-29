<?php

namespace App\Modules\Build\Enums;

/**
 * Lot d'un devis de chantier (clé `lines[].lot` de `construction_quotes`) — F7.3.e2.
 *
 * Un devis de construction se lit par corps d'état : c'est ainsi que le client
 * compare deux offres et que l'équipe vérifie qu'aucun poste n'a été oublié. Ces
 * lots servent aussi de vocabulaire commun avec les prestataires BTP affectés au
 * chantier (F7.3.e3).
 *
 * L'ordre des cas suit l'ordre d'exécution d'un chantier : il donne l'ordre
 * d'affichage par défaut du devis, sans qu'on ait à le stocker.
 */
enum ConstructionLot: string
{
    case ETUDES = 'etudes';
    case TERRASSEMENT = 'terrassement';
    case FONDATIONS = 'fondations';
    case GROS_OEUVRE = 'gros_oeuvre';
    case CHARPENTE_COUVERTURE = 'charpente_couverture';
    case MENUISERIE = 'menuiserie';
    case PLOMBERIE = 'plomberie';
    case ELECTRICITE = 'electricite';
    case FINITIONS = 'finitions';
    case AMENAGEMENTS_EXTERIEURS = 'amenagements_exterieurs';
    case MAIN_OEUVRE = 'main_oeuvre';
    case DIVERS = 'divers';

    public function label(): string
    {
        return match ($this) {
            self::ETUDES => 'Études & permis',
            self::TERRASSEMENT => 'Terrassement',
            self::FONDATIONS => 'Fondations',
            self::GROS_OEUVRE => 'Gros œuvre',
            self::CHARPENTE_COUVERTURE => 'Charpente & couverture',
            self::MENUISERIE => 'Menuiserie',
            self::PLOMBERIE => 'Plomberie',
            self::ELECTRICITE => 'Électricité',
            self::FINITIONS => 'Finitions',
            self::AMENAGEMENTS_EXTERIEURS => 'Aménagements extérieurs',
            self::MAIN_OEUVRE => 'Main d’œuvre',
            self::DIVERS => 'Divers',
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
