<?php

namespace App\Modules\Mobility\Enums;

/**
 * Catégories de véhicules / moyens de transport (colonne `vehicles.type`).
 *
 * Le cahier des charges distingue explicitement ces catégories. La PIROGUE est
 * traitée à part (transport fluvial/maritime) : ses contrôles de conformité
 * diffèrent du transport motorisé (cf. VehicleComplianceChecker, B7.3).
 */
enum VehicleType: string
{
    case VOITURE_PARTICULIERE = 'voiture_particuliere';
    case VOITURE_TOURISTIQUE = 'voiture_touristique';
    case NAVETTE_AIBD = 'navette_aibd'; // navette aéroportuaire (Aéroport Blaise Diagne)
    case BUS = 'bus';
    case MINIBUS = 'minibus';
    case QUATRE_QUATRE = 'quatre_quatre'; // 4x4
    case PIROGUE = 'pirogue';
    case CHAUFFEUR = 'chauffeur'; // mise à disposition d'un chauffeur

    public function label(): string
    {
        return match ($this) {
            self::VOITURE_PARTICULIERE => 'Voiture particulière',
            self::VOITURE_TOURISTIQUE => 'Voiture touristique',
            self::NAVETTE_AIBD => 'Navette aéroportuaire (AIBD)',
            self::BUS => 'Bus',
            self::MINIBUS => 'Minibus',
            self::QUATRE_QUATRE => '4x4',
            self::PIROGUE => 'Pirogue',
            self::CHAUFFEUR => 'Chauffeur',
        };
    }

    /**
     * Transport motorisé terrestre (tout sauf la pirogue).
     */
    public function isMotorized(): bool
    {
        return $this !== self::PIROGUE;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
