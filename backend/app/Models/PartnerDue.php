<?php

namespace App\Models;

use App\Enums\PartnerDueStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Une dette de Kaikun envers un partenaire (F8.16.a) — voir la migration
 * `create_partner_dues_table` pour le raisonnement complet.
 *
 * Transversale par nature (elle naît de cinq univers), donc rangée dans
 * `app/Models` avec `Booking`, `Review` et `Media`, et non dans un module.
 */
class PartnerDue extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'beneficiary_id',
        'source_type',
        'source_id',
        'gross_xof',
        'commission_xof',
        'net_xof',
        'status',
        'eligible_at',
        'payout_id',
        'cancelled_reason',
        'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gross_xof' => 'integer',
            'commission_xof' => 'integer',
            'net_xof' => 'integer',
            'status' => PartnerDueStatus::class,
            'eligible_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** La réservation ou la mission qui a fait naître la dette. */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** Le propriétaire ou le prestataire à qui l'argent est dû. */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_id');
    }

    /** Le versement qui a soldé la dette (null tant qu'elle est impayée). */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(PartnerPayout::class, 'payout_id');
    }

    /** Dettes encore dues (ni payées ni annulées) — l'encours d'un partenaire. */
    public function scopeVivantes(Builder $query): Builder
    {
        return $query->whereIn('status', [
            PartnerDueStatus::EN_ATTENTE->value,
            PartnerDueStatus::EXIGIBLE->value,
        ]);
    }

    /** Dettes que l'on peut mettre dans un versement dès maintenant. */
    public function scopePayables(Builder $query): Builder
    {
        return $query->where('status', PartnerDueStatus::EXIGIBLE->value)
            ->whereNull('payout_id');
    }
}
