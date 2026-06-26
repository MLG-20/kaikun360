<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Département du Sénégal (référentiel géographique). 46 départements au total.
 */
class Department extends Model
{
    protected $fillable = ['region_id', 'name'];

    /**
     * La région à laquelle appartient le département.
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Les communes du département.
     */
    public function communes(): HasMany
    {
        return $this->hasMany(Commune::class);
    }
}
