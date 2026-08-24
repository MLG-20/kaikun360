<?php

namespace App\Modules\Pro\Enums;

/**
 * Statut de modération d'une catégorie de service prestataire (colonne
 * `provider_categories.status`).
 *
 * Une catégorie proposée par un prestataire entre EN_ATTENTE : utilisable
 * immédiatement par son auteur, mais invisible pour les autres tant qu'un admin
 * ne l'a pas validée (`ProviderCategoryValidator`, file de modération générique).
 */
enum ProviderCategoryStatus: string
{
    case EN_ATTENTE = 'en_attente';
    case VALIDE = 'valide';
    case REFUSE = 'refuse';

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'En attente',
            self::VALIDE => 'Validée',
            self::REFUSE => 'Refusée',
        };
    }
}
