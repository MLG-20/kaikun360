<?php

namespace App\Modules\Manage\Models;

use App\Models\User;
use App\Modules\Manage\Enums\RentStatus;
use Database\Factories\RentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Échéance de loyer rattachée à un mandat de gestion locative (module Manage).
 */
class Rent extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'mandate_id',
        'tenant_id',
        'tenant_name',
        'period_label',
        'due_date',
        'amount_xof',
        'status',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount_xof' => 'integer',
            'status' => RentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function mandate(): BelongsTo
    {
        return $this->belongsTo(ManagementMandate::class, 'mandate_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    protected static function newFactory(): RentFactory
    {
        return RentFactory::new();
    }
}
