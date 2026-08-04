<?php

namespace App\Modules\TeamBuilding\Models;

use App\Models\Booking;
use App\Modules\TeamBuilding\Enums\TeamBuildingQuoteStatus;
use Database\Factories\TeamBuildingQuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Devis composé multi-prestataires pour une demande de team building (B9.2).
 */
class TeamBuildingQuote extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'request_id',
        'lines',
        'subtotal_xof',
        'margin_rate',
        'margin_xof',
        'total_xof',
        'status',
        'sent_at',
        'accepted_at',
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
            'status' => TeamBuildingQuoteStatus::class,
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(TeamBuildingRequest::class, 'request_id');
    }

    /**
     * La réservation payable née de l'acceptation de ce devis (F8.14).
     *
     * ⚠️ Le devis est LUI-MÊME la cible polymorphe (`bookable_type`), comme le
     * devis sur-mesure générique en F8.11 : un séminaire assemblé sur mesure n'a
     * aucune fiche au catalogue à désigner — ce qui est vendu, c'est exactement
     * ce devis, avec ses lignes et son total. `bookings` étant polymorphe depuis
     * B3.3, aucune migration n'a été nécessaire.
     *
     * `morphOne` et non `morphMany` : un devis accepté ne se règle qu'une fois,
     * et c'est cette unicité qui rend la conversion idempotente.
     */
    public function booking(): MorphOne
    {
        return $this->morphOne(Booking::class, 'bookable');
    }

    protected static function newFactory(): TeamBuildingQuoteFactory
    {
        return TeamBuildingQuoteFactory::new();
    }
}
