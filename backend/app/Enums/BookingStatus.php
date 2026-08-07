<?php

namespace App\Enums;

/**
 * Statuts d'une réservation (table `bookings`, couche transversale, cf. phase B11).
 *
 * Le statut de réservation est VOLONTAIREMENT distinct du statut de paiement
 * (voir PaymentStatus) : une réservation peut être annulée indépendamment de
 * l'état financier. Le cahier des charges distingue trois origines d'annulation
 * (client / prestataire / admin) pour la traçabilité.
 */
enum BookingStatus: string
{
    case EN_ATTENTE = 'en_attente';            // créée, en attente de confirmation
    case CONFIRMEE = 'confirmee';              // confirmée (généralement après paiement)
    case EN_COURS = 'en_cours';                // service en cours de réalisation
    case TERMINEE = 'terminee';                // service terminé
    case ANNULEE_CLIENT = 'annulee_client';
    case ANNULEE_PRESTATAIRE = 'annulee_prestataire';
    case ANNULEE_ADMIN = 'annulee_admin';

    /**
     * Libellé lisible (français).
     */
    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'En attente',
            self::CONFIRMEE => 'Confirmée',
            self::EN_COURS => 'En cours',
            self::TERMINEE => 'Terminée',
            self::ANNULEE_CLIENT => 'Annulée (client)',
            self::ANNULEE_PRESTATAIRE => 'Annulée (prestataire)',
            self::ANNULEE_ADMIN => 'Annulée (admin)',
        };
    }

    /**
     * Indique si le statut correspond à une annulation (quelle qu'en soit l'origine).
     */
    public function estAnnulee(): bool
    {
        return in_array($this, [
            self::ANNULEE_CLIENT,
            self::ANNULEE_PRESTATAIRE,
            self::ANNULEE_ADMIN,
        ], true);
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

    /**
     * Les valeurs brutes des statuts d'annulation.
     *
     * ⚠️ Extrait ici en F8.23 parce que ce calcul était recopié à l'identique
     * dans `MobilityServiceController`, `MobilityServiceBookingController` et,
     * désormais, la correction d'un départ : partout où l'on compte des places
     * prises, une réservation annulée rend sa place. Trois copies d'une même
     * règle, c'est trois occasions qu'elles divergent le jour où un quatrième
     * statut d'annulation apparaît.
     *
     * @return array<int, string>
     */
    public static function valeursAnnulees(): array
    {
        return array_map(
            fn (self $statut) => $statut->value,
            array_filter(self::cases(), fn (self $statut) => $statut->estAnnulee()),
        );
    }
}
