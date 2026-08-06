<?php

namespace App\Modules\Manage\Models;

use App\Models\User;
use App\Modules\Manage\Enums\OwnerPayoutStatus;
use Database\Factories\OwnerPayoutFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reversement au propriétaire (module Manage).
 */
class OwnerPayout extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'mandate_id',
        'owner_id',
        'period_label',
        'amount_xof',
        'status',
        'paid_at',
        'proof_path',
        'proof_disk',
        'proof_original_name',
        'paid_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_xof' => 'integer',
            'status' => OwnerPayoutStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function mandate(): BelongsTo
    {
        return $this->belongsTo(ManagementMandate::class, 'mandate_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** L'agent qui a constaté le virement (2026-08-06). */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /** Le justificatif a-t-il bien été déposé ? */
    public function hasProof(): bool
    {
        return $this->proof_path !== null;
    }

    protected static function newFactory(): OwnerPayoutFactory
    {
        return OwnerPayoutFactory::new();
    }
}
