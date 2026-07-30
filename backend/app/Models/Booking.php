<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\CautionStatus;
use App\Enums\HousekeepingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Réservation (modèle transversal, introduit en B3.3, enrichi en B11).
 *
 * Polymorphe : `bookable` peut être une nuitée (Stay), un véhicule, une
 * expérience… Le statut métier s'appuie sur l'enum BookingStatus.
 */
class Booking extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'user_id',
        'bookable_type',
        'bookable_id',
        'start_date',
        'end_date',
        'guests',
        'amount_xof',
        'commission_xof',
        'caution_xof',
        'caution_status',
        'status',
        'cancelled_at',
        'checked_in_at',
        'checked_out_at',
        'housekeeping_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'guests' => 'integer',
            'amount_xof' => 'integer',
            'commission_xof' => 'integer',
            'caution_xof' => 'integer',
            'caution_status' => CautionStatus::class,
            'status' => BookingStatus::class,
            'cancelled_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'housekeeping_status' => HousekeepingStatus::class,
        ];
    }

    /**
     * Horodatage automatique de l'annulation : dès qu'une réservation passe à un
     * statut d'annulation (quelle qu'en soit l'origine), on fige `cancelled_at`.
     * Distinct du statut de paiement (cf. cahier des charges B11).
     */
    protected static function booted(): void
    {
        static::saving(function (Booking $booking): void {
            if ($booking->status instanceof BookingStatus
                && $booking->status->estAnnulee()
                && $booking->cancelled_at === null) {
                $booking->cancelled_at = now();
            }
        });
    }

    /**
     * La cible réservée (Stay, Vehicle, Experience…).
     */
    public function bookable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Le client.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Les paiements rattachés (acompte, solde, remboursement) — B14.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Vrai si la réservation est **entièrement soldée** (F7.3.h).
     *
     * ⚠️ Le sens a changé : jusqu'ici, un seul paiement encaissé suffisait à
     * considérer la réservation comme payée. Depuis l'ouverture des acomptes, il
     * faut que le total encaissé couvre le montant — sans quoi un acompte
     * empêcherait le client de verser son solde.
     */
    public function estPayee(): bool
    {
        return $this->resteAPayer() <= 0;
    }

    /**
     * Total réellement ENCAISSÉ sur la réservation (F7.3.h).
     *
     * Ne comptent que les paiements `complete` : un règlement initié ou en attente
     * de confirmation manuelle n'a rien apporté. Les remboursements sortent donc
     * naturellement du calcul (leur statut n'est plus `complete`).
     */
    public function montantPaye(): int
    {
        // Si la relation a été pré-chargée (listes du back-office), on calcule en
        // mémoire : sans cela, afficher le reste dû sur une page de 20 paiements
        // déclencherait 20 requêtes de plus.
        $payments = $this->relationLoaded('payments')
            ? $this->payments
            : $this->payments()->get();

        return (int) $payments
            ->filter(fn (Payment $payment) => $payment->status === PaymentStatus::COMPLETE)
            ->sum('amount_xof');
    }

    /**
     * Reste à payer (jamais négatif) — le « solde » du CDC §6.
     *
     * Un trop-perçu ne devient pas une dette de la plateforme envers le client :
     * il se règle par un remboursement, pas par un reste à payer négatif.
     */
    public function resteAPayer(): int
    {
        return max(0, (int) $this->amount_xof - $this->montantPaye());
    }

    /**
     * Nature d'un règlement de ce montant, DÉDUITE de l'état de la réservation.
     *
     * Règle : ce qui laisse un reliquat est un acompte ; ce qui solde après un
     * premier versement est un solde ; ce qui règle tout d'un coup est intégral.
     * On la déduit plutôt que de la faire saisir — un libellé choisi à la main
     * finirait par mentir sur les chiffres.
     */
    public function natureDuReglement(int $montant): PaymentKind
    {
        $dejaPaye = $this->montantPaye();

        if ($montant < $this->resteAPayer()) {
            return PaymentKind::ACOMPTE;
        }

        return $dejaPaye > 0 ? PaymentKind::SOLDE : PaymentKind::INTEGRAL;
    }
}
