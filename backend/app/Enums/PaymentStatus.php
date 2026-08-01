<?php

namespace App\Enums;

/**
 * Statuts d'un paiement (table `payments`, cf. phase B14 — PayTech).
 *
 * Ces statuts internes sont alignés sur les ÉVÉNEMENTS PayTech (`type_event` :
 * `sale_complete`, `sale_canceled`, `refund_complete`…), complétés des états
 * techniques propres à Kaikun (initié, en attente).
 *
 * Règle de sécurité (B14) : une réservation n'est confirmée que sur un webhook
 * PayTech VÉRIFIÉ passant au statut COMPLETE — jamais sur une simple capture client.
 */
enum PaymentStatus: string
{
    case INITIE = 'initie';          // intention de paiement créée côté Kaikun
    case EN_ATTENTE = 'en_attente';  // en attente de confirmation du PSP
    case AUTORISE = 'autorise';      // fonds autorisés sans capture (aucun équivalent PayTech)
    case COMPLETE = 'complete';      // PayTech `sale_complete` (encaissé) → confirme la résa
    case REFUSE = 'refuse';          // PayTech `transfer_failed`
    case ANNULE = 'annule';          // PayTech `sale_canceled`
    case REMBOURSE = 'rembourse';    // PayTech `refund_complete`

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
     * Traduit un `type_event` PayTech vers le statut interne, ou `null` si
     * l'événement est inconnu — auquel cas le webhook doit être REJETÉ plutôt
     * qu'interprété : deviner le sens d'un événement de paiement, c'est risquer
     * de confirmer une réservation jamais réglée.
     *
     * ⚠️ **Réécrit en F8.5.** Cette table contenait `AUTHORIZED`, `COMPLETED`,
     * `DECLINED`… qui ne sont pas des valeurs PayTech : aucune notification
     * réelle n'aurait été reconnue. Les événements documentés sont
     * `sale_complete`, `sale_canceled`, `transfer_success`, `transfer_failed`
     * et `refund_complete`.
     *
     * Il n'existe pas d'équivalent PayTech à `AUTORISE` (autorisation sans
     * capture) : le PSP ne notifie qu'une vente aboutie ou annulée.
     */
    public static function fromPaytech(string $typeEvent): ?self
    {
        return match (strtolower(trim($typeEvent))) {
            'sale_complete', 'sale_completed' => self::COMPLETE,
            'sale_canceled', 'sale_cancelled' => self::ANNULE,
            'refund_complete', 'refund_completed' => self::REMBOURSE,
            // Les transferts sortants (paiement d'un prestataire) empruntent le
            // même canal de notification que les ventes.
            'transfer_success' => self::COMPLETE,
            'transfer_failed' => self::REFUSE,
            default => null,
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
