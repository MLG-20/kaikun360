<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Commune du Sénégal (référentiel géographique). ~557 communes au total.
 *
 * Importées depuis database/data/communes.json par CommunesSeeder.
 */
class Commune extends Model
{
    protected $fillable = ['department_id', 'name', 'type'];

    /**
     * Le département auquel appartient la commune.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
