<?php

namespace App\Modules\Pro\Enums;

/**
 * Statut de validation d'un prestataire marketplace (colonne `providers.status`).
 *
 * Un prestataire ne peut publier de service public qu'une fois VALIDE. La
 * validation/suspension synchronise le `verification_status` du profil (Core),
 * ce qui pilote aussi la publication dans Explore (B6) et Mobility (B7).
 */
enum ProviderStatus: string
{
    case EN_ATTENTE = 'en_attente';
    case VALIDE = 'valide';
    case REFUSE = 'refuse';
    case SUSPENDU = 'suspendu';

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'En attente',
            self::VALIDE => 'Validé',
            self::REFUSE => 'Refusé',
            self::SUSPENDU => 'Suspendu',
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
