<?php

namespace App\Modules\Core\Models;

use App\Models\User;
use App\Modules\Core\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pièce justificative déposée par un utilisateur.
 *
 * Le fichier est stocké sur un disque privé ; ce modèle ne porte que les
 * métadonnées et le chemin.
 */
class UserDocument extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'size' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
