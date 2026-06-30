<?php

namespace App\Modules\Diaspora\Models;

use App\Models\Report;
use App\Models\User;
use App\Modules\Diaspora\Enums\DiasporaPriority;
use App\Modules\Diaspora\Enums\DiasporaProjectStatus;
use App\Modules\Diaspora\Enums\DiasporaProjectType;
use Database\Factories\DiasporaProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Projet diaspora piloté à distance (module Diaspora).
 *
 * Appartient à un client et, après affectation, à un agent dédié. Les rapports
 * de suivi réutilisent le modèle transversal polymorphe `Report` (commun à Build).
 */
class DiasporaProject extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'client_id',
        'agent_id',
        'project_type',
        'residence_country',
        'budget_xof',
        'description',
        'priority',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'project_type' => DiasporaProjectType::class,
            'budget_xof' => 'integer',
            'priority' => DiasporaPriority::class,
            'status' => DiasporaProjectStatus::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * Les rapports de suivi (relation polymorphe, partagée avec Build, B5.2).
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    protected static function newFactory(): DiasporaProjectFactory
    {
        return DiasporaProjectFactory::new();
    }
}
