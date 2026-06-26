<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Région du Sénégal (référentiel géographique). 14 régions au total.
 */
class Region extends Model
{
    protected $fillable = ['name'];

    /**
     * Les départements de la région.
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }
}
