<?php

namespace App\Modules\Build\Models;

use App\Models\Booking;
use App\Models\User;
use App\Modules\Build\Enums\ConstructionQuoteStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Devis de chantier ventilé par lot (F7.3.e2).
 *
 * Les lignes et les totaux sont FIGÉS à la composition (cf. migration) : un devis
 * envoyé au client ne doit plus bouger, même si le barème ou la marge change.
 */
class ConstructionQuote extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'construction_request_id',
        'lines',
        'subtotal_xof',
        'margin_rate',
        'margin_xof',
        'total_xof',
        'valid_until',
        'status',
        'sent_at',
        'accepted_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lines' => 'array',
            'subtotal_xof' => 'integer',
            'margin_rate' => 'decimal:2',
            'margin_xof' => 'integer',
            'total_xof' => 'integer',
            'valid_until' => 'date',
            'status' => ConstructionQuoteStatus::class,
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * La réservation payable née de l'acceptation de ce devis (F8.14).
     *
     * Même modélisation que le devis sur-mesure générique (F8.11) et que le
     * devis team building : le devis est LUI-MÊME la cible polymorphe — un
     * chantier n'a aucune fiche au catalogue à désigner.
     */
    public function booking(): MorphOne
    {
        return $this->morphOne(Booking::class, 'bookable');
    }

    public function constructionRequest(): BelongsTo
    {
        return $this->belongsTo(ConstructionRequest::class);
    }

    /** Agent ou admin qui a chiffré le devis. */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
