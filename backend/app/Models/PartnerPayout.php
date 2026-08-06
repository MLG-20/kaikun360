<?php

namespace App\Models;

use App\Enums\PartnerPayoutStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un versement effectué à un partenaire (F8.16.a) — voir la migration
 * `create_partner_payouts_table` pour le raisonnement complet.
 *
 * Un versement solde **plusieurs dettes** : c'est le lot, et c'est lui qui rend
 * la cadence (hebdomadaire, mensuelle, à la demande) libre sans toucher au
 * schéma.
 */
class PartnerPayout extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'beneficiary_id',
        'amount_xof',
        'status',
        'method',
        'external_reference',
        'paid_at',
        'proof_path',
        'proof_disk',
        'proof_original_name',
        'note',
        'created_by',
        'paid_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_xof' => 'integer',
            'status' => PartnerPayoutStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    /** Les dettes soldées par ce versement. */
    public function dues(): HasMany
    {
        return $this->hasMany(PartnerDue::class, 'payout_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_id');
    }

    /** L'agent qui a préparé le lot. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** L'agent qui a constaté le virement. */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
