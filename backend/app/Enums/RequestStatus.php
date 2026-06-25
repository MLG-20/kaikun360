<?php

namespace App\Enums;

/**
 * Statuts d'une demande client (couche transversale "Requests", cf. phase B11).
 *
 * Machine à états STRICTE — l'ordre des étapes est :
 *   RECU → VERIFICATION → VISITE → DEVIS → NEGOCIATION → CLOTURE
 *
 * Les transitions autorisées seront validées côté backend en phase B11
 * (aucune transition invalide ne doit être acceptée par l'API).
 */
enum RequestStatus: string
{
    case RECU = 'recu';
    case VERIFICATION = 'verification';
    case VISITE = 'visite';
    case DEVIS = 'devis';
    case NEGOCIATION = 'negociation';
    case CLOTURE = 'cloture';

    /**
     * Libellé lisible (français).
     */
    public function label(): string
    {
        return match ($this) {
            self::RECU => 'Reçu',
            self::VERIFICATION => 'En vérification',
            self::VISITE => 'Visite',
            self::DEVIS => 'Devis',
            self::NEGOCIATION => 'Négociation',
            self::CLOTURE => 'Clôturé',
        };
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
