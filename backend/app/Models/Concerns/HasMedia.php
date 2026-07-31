<?php

namespace App\Models\Concerns;

use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Galerie média d'une ressource illustrable (B12.1, complété en F8.1).
 *
 * Mutualise les deux relations dont toute ressource publiable a besoin :
 *
 *   - `media()`      : ce que voit le PUBLIC — médias actifs uniquement,
 *                      image de une en tête puis ordre choisi par le déposant ;
 *   - `allMedia()`   : ce que voit le BACK-OFFICE — y compris les médias
 *                      masqués par un modérateur, sans quoi un agent ne
 *                      pourrait jamais réafficher une photo qu'il a écartée.
 *
 * À appliquer à toute classe listée dans `Media::TYPES`. Historiquement seul
 * Property portait cette relation : véhicules et expériences acceptaient déjà
 * des dépôts via `POST media/upload` sans qu'aucun écran ne puisse les relire
 * (dette laissée par le commentaire « sera branchée en B12 » de Vehicle).
 */
trait HasMedia
{
    /**
     * Médias VISIBLES de la ressource (catalogue public, fiche publique).
     *
     * Des photos nettes sont déterminantes pour la confiance des clients : le
     * catalogue public et la fiche s'appuient sur cette relation.
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')
            ->visible()
            ->orderByDesc('is_primary')
            ->orderBy('position');
    }

    /**
     * TOUS les médias, masqués compris — réservé à la modération.
     *
     * Ne jamais exposer cette relation sur une route publique : elle contient
     * précisément les contenus qu'un agent a choisi de retirer des annonces.
     */
    public function allMedia(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')
            ->orderByDesc('is_primary')
            ->orderBy('position');
    }
}
