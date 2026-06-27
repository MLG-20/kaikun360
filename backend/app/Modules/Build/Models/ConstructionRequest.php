<?php

namespace App\Modules\Build\Models;

use App\Models\Report;
use App\Models\User;
use App\Modules\Build\Enums\ConstructionObjective;
use App\Modules\Build\Enums\ConstructionRequestStatus;
use App\Modules\Build\Enums\FinishLevel;
use Database\Factories\ConstructionRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Demande de construction / rénovation (module Build).
 *
 * Appartient à un client (User). Recevra des rapports de suivi (B5.2), des
 * jalons de chantier (B5.3) et des devis via la couche transversale Quotes (B11).
 */
class ConstructionRequest extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'client_id',
        'objective',
        'city',
        'surface_m2',
        'budget_xof',
        'finish_level',
        'description',
        'estimated_cost_xof',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'objective' => ConstructionObjective::class,
            'finish_level' => FinishLevel::class,
            'status' => ConstructionRequestStatus::class,
            'surface_m2' => 'integer',
            'budget_xof' => 'integer',
            'estimated_cost_xof' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Les rapports de suivi de chantier (relation polymorphe, B5.2).
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    protected static function newFactory(): ConstructionRequestFactory
    {
        return ConstructionRequestFactory::new();
    }
}
