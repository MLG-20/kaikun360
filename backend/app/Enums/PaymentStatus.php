<?php

namespace App\Enums;

/**
 * Statuts d'un paiement (table `payments`, cf. phase B14 — PayTech).
 *
 * Ces statuts internes sont alignés sur les états renvoyés par le PSP PayTech
 * (AUTHORIZED, COMPLETED, DECLINED, CANCELLED), complétés des états techniques
 * propres à Kaikun (initié, en attente, remboursé).
 *
 * Règle de sécurité (B14) : une réservation n'est confirmée que sur un webhook
 * PayTech VÉRIFIÉ passant au statut COMPLETE — jamais sur une simple capture client.
 */
enum PaymentStatus: string
{
    case INITIE = 'initie';          // intention de paiement créée côté Kaikun
    case EN_ATTENTE = 'en_attente';  // en attente de confirmation du PSP
    case AUTORISE = 'autorise';      // PayTech AUTHORIZED (fonds autorisés)
    case COMPLETE = 'complete';      // PayTech COMPLETED (encaissé) → confirme la résa
    case REFUSE = 'refuse';          // PayTech DECLINED
    case ANNULE = 'annule';          // PayTech CANCELLED
    case REMBOURSE = 'rembourse';    // remboursement effectué (caution / annulation éligible)

    /**
     * Libellé lisible (français).
     */
    public function label(): string
    {
        return match ($this) {
            self::INITIE => 'Initié',
            self::EN_ATTENTE => 'En attente',
            self::AUTORISE => 'Autorisé',
            self::COMPLETE => 'Complété',
            self::REFUSE => 'Refusé',
            self::ANNULE => 'Annulé',
            self::REMBOURSE => 'Remboursé',
        };
    }

    /**
     * Indique si le paiement est dans un état "réussi" qui confirme la réservation.
     */
    public function estReussi(): bool
    {
        return $this === self::COMPLETE;
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
