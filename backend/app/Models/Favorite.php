<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Favori POLYMORPHE (couche transversale, tous univers).
 *
 * Un utilisateur sauvegarde un élément favorisable (bien, nuitée, véhicule,
 * expérience, service de mobilité) pour le retrouver plus tard. La cible est
 * désignée par le couple `favoritable_type` / `favoritable_id` (nom de classe
 * complet stocké, même convention que `bookings.bookable_*`). Voir le registre
 * transversal `App\Support\Favoritables` pour la correspondance slug ↔ modèle.
 */
class Favorite extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'favoritable_type',
        'favoritable_id',
    ];

    /** L'utilisateur qui a sauvegardé l'élément. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** L'élément favorisé (bien, nuitée, véhicule, expérience, mobilité). */
    public function favoritable(): MorphTo
    {
        return $this->morphTo();
    }
}
