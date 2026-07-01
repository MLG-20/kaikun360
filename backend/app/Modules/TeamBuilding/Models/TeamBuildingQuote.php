<?php

namespace App\Modules\TeamBuilding\Models;

use App\Modules\TeamBuilding\Enums\TeamBuildingQuoteStatus;
use Database\Factories\TeamBuildingQuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    protected static function newFactory(): TeamBuildingQuoteFactory
    {
        return TeamBuildingQuoteFactory::new();
    }
}
