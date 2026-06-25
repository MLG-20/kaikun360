<?php

namespace App\Modules\Core\Enums;

/**
 * Statut d'un compte utilisateur (colonne `users.status`).
 *
 * Cycle : EN_ATTENTE_VERIFICATION (à l'inscription) → ACTIF (après vérification
 * email/téléphone). Un compte peut ensuite être SUSPENDU (sanction temporaire)
 * ou DESACTIVE (par l'admin ou l'utilisateur lui-même).
 *
 * Règle de sécurité (B15) : un compte non vérifié ne doit pas accéder aux
 * fonctionnalités complètes.
 */
enum UserStatus: string
{
    case EN_ATTENTE_VERIFICATION = 'en_attente_verification';
    case ACTIF = 'actif';
    case SUSPENDU = 'suspendu';
    case DESACTIVE = 'desactive';

    /**
     * Libellé lisible (français).
     */
    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE_VERIFICATION => 'En attente de vérification',
            self::ACTIF => 'Actif',
            self::SUSPENDU => 'Suspendu',
            self::DESACTIVE => 'Désactivé',
        };
    }

    /**
     * Indique si le compte est pleinement opérationnel.
     */
    public function estActif(): bool
    {
        return $this === self::ACTIF;
    }

    /**
     * Liste des valeurs brutes (pour la validation).
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
