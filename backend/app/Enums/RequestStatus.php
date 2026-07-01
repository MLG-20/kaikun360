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
     * Transitions autorisées depuis ce statut (machine à états stricte, B11).
     *
     * On avance d'une étape à la fois le long de la chaîne ; la clôture reste
     * possible à toute étape (abandon/fin anticipée). Aucun retour en arrière ni
     * saut d'étape n'est autorisé.
     *
     * @return array<int, self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::RECU => [self::VERIFICATION, self::CLOTURE],
            self::VERIFICATION => [self::VISITE, self::CLOTURE],
            self::VISITE => [self::DEVIS, self::CLOTURE],
            self::DEVIS => [self::NEGOCIATION, self::CLOTURE],
            self::NEGOCIATION => [self::CLOTURE],
            self::CLOTURE => [],
        };
    }

    /**
     * La transition vers `$target` est-elle autorisée depuis ce statut ?
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedNext(), true);
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
