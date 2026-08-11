<?php

namespace App\Models;

use App\Support\Heroes\HeroCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Bandeau d'en-tête d'une page publique (F12).
 *
 * Une ligne = une surcharge back-office pour une clé de bandeau connue du
 * {@see HeroCatalog}. On n'interroge presque jamais ce
 * modèle directement : passer par le catalogue, qui applique l'héritage
 * d'image et met le résultat en cache.
 */
class HeroBanner extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'image_path',
        'eyebrow',
        'title',
        'lead',
        'updated_by',
    ];

    /**
     * URL publique de l'image, ou `null` si ce bandeau n'en porte pas.
     *
     * Le chemin est stocké relatif au disque `public` : c'est lui qui sait
     * construire l'URL, pas le contrôleur — le jour où les médias partent sur
     * un S3, rien d'autre ne bouge.
     */
    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
