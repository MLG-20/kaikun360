<?php

namespace App\Models;

use App\Modules\Immo\Models\Property;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Commune du Sénégal (référentiel géographique). ~557 communes au total.
 *
 * Importées depuis database/data/communes.json par CommunesSeeder, puis
 * maintenues par l'équipe depuis le back-office (F7.2.l, écran Paramètres).
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

    /**
     * Les biens localisés dans cette commune.
     *
     * Relation d'USAGE : elle n'existe que pour compter les rattachements avant
     * une suppression au back-office. La FK `properties.commune_id` est en
     * `nullOnDelete` — supprimer une commune utilisée effacerait donc
     * silencieusement la localisation des biens, d'où le garde-fou.
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /**
     * Les comptes utilisateurs déclarant cette commune (même logique d'usage).
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
