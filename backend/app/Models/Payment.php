<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transaction de paiement (modèle transversal, B14).
 *
 * Reliée à une réservation ; portée par un PSP (PayTech). Le montant et la
 * commission sont figés à la création ; le statut évolue au gré des
 * notifications VÉRIFIÉES du PSP.
 */
class Payment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'booking_id',
        'provider',
        'amount_xof',
        'commission_xof',
        'status',
        'mode',
        'provider_reference',
        'signature_verified',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_xof' => 'integer',
            'commission_xof' => 'integer',
            'status' => PaymentStatus::class,
            'signature_verified' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
