<?php

namespace App\Models;

use App\Enums\BookingStatus;
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
        'caution_xof',
        'status',
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
            'caution_xof' => 'integer',
            'status' => BookingStatus::class,
        ];
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
