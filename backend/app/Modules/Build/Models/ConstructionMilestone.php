<?php

namespace App\Modules\Build\Models;

use App\Modules\Build\Enums\MilestoneStatus;
use Database\Factories\ConstructionMilestoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jalon (étape) d'un chantier de construction (module Build, B5.3).
 */
class ConstructionMilestone extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'construction_request_id',
        'name',
        'position',
        'status',
        'planned_date',
        'actual_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'status' => MilestoneStatus::class,
            'planned_date' => 'date',
            'actual_date' => 'date',
        ];
    }

    public function constructionRequest(): BelongsTo
    {
        return $this->belongsTo(ConstructionRequest::class);
    }

    protected static function newFactory(): ConstructionMilestoneFactory
    {
        return ConstructionMilestoneFactory::new();
    }
}
