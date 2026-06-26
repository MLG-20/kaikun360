<?php

namespace App\Modules\Immo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pièce justificative rattachée à un bien immobilier.
 *
 * Fichier stocké sur disque privé ; ce modèle ne porte que les métadonnées.
 */
class PropertyDocument extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'property_id',
        'type',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'validation_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
