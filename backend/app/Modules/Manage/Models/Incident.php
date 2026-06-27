<?php

namespace App\Modules\Manage\Models;

use App\Models\User;
use App\Modules\Immo\Models\Property;
use App\Modules\Manage\Enums\IncidentStatus;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Incident lié à un bien (signalement) — module Manage.
 */
class Incident extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'property_id',
        'reported_by',
        'title',
        'description',
        'priority',
        'status',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => IncidentStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    protected static function newFactory(): IncidentFactory
    {
        return IncidentFactory::new();
    }
}
