<?php

namespace App\Modules\Core\Models;

use App\Models\User;
use App\Modules\Core\Enums\ProfileType;
use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle Profil — la "casquette métier" d'un utilisateur.
 *
 * Relation 1–1 avec User (un utilisateur = un profil). Porte le type de profil,
 * l'état de vérification (KYC) et les préférences.
 */
class Profile extends Model
{
    use HasFactory;

    /**
     * Champs autorisés à l'assignation de masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'verification_status',
        'preferences',
    ];

    /**
     * Conversions automatiques des attributs.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProfileType::class,   // typé via l'enum ProfileType
            'preferences' => 'array',        // JSON <-> tableau PHP automatiquement
        ];
    }

    /**
     * Relation inverse : le profil appartient à un utilisateur.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Indique à Eloquent où trouver la factory de ce modèle.
     * Nécessaire car le modèle vit dans un module (hors de App\Models),
     * là où la résolution automatique des factories ne s'applique pas.
     */
    protected static function newFactory(): ProfileFactory
    {
        return ProfileFactory::new();
    }
}
