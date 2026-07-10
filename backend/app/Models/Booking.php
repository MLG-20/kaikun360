<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\CautionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
}
