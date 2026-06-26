<?php

namespace App\Modules\Manage\Models;

use App\Models\User;
use App\Modules\Immo\Models\Property;
use App\Modules\Manage\Enums\MandateStatus;
use Database\Factories\ManagementMandateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mandat de gestion locative (module Manage).
 *
 * Lie un bien et son propriétaire à Kaikun, avec un taux de commission.
 */
class ManagementMandate extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'property_id',
        'owner_id',
        'commission_rate',
        'start_date',
        'end_date',
        'status',
        'terms',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => MandateStatus::class,
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    protected static function newFactory(): ManagementMandateFactory
    {
        return ManagementMandateFactory::new();
    }
}
