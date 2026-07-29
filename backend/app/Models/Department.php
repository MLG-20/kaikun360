<?php

namespace App\Models;

use App\Modules\Immo\Models\Property;
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

    /**
     * Les biens rattachés directement au département (relation d'usage,
     * consommée par le garde-fou de suppression du back-office — F7.2.l).
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /**
     * Les comptes utilisateurs rattachés au département (même logique d'usage).
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
