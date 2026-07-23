<?php

namespace App\Modules\Pro\Models;

use App\Models\User;
use App\Modules\Pro\Enums\ProviderCategory;
use App\Modules\Pro\Enums\ProviderStatus;
use Database\Factories\ProviderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Profil prestataire marketplace (module Pro).
 *
 * Relation 1–1 avec un utilisateur. Un prestataire ne publie de service public
 * qu'une fois VALIDE (statut synchronisé avec le profil Core).
 */
class Provider extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'business_name',
        'category',
        'bio',
        'status',
        'validated_at',
        'validated_by',
        'warnings_count',
        'sanction_note',
        'rating_avg',
        'rating_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ProviderCategory::class,
            'status' => ProviderStatus::class,
            'validated_at' => 'datetime',
            'warnings_count' => 'integer',
            'rating_avg' => 'decimal:2',
            'rating_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Les documents de certification fournis à l'inscription.
     */
    public function certifications(): HasMany
    {
        return $this->hasMany(ProviderCertification::class);
    }

    /**
     * Les missions affectées au prestataire (B10.3).
     */
    public function missions(): HasMany
    {
        return $this->hasMany(ProviderMission::class);
    }

    /**
     * Le planning hebdomadaire récurrent (une ligne par jour ouvré) — F5.4.
     */
    public function weeklyAvailabilities(): HasMany
    {
        return $this->hasMany(ProviderWeeklyAvailability::class);
    }

    /**
     * Les périodes d'indisponibilité ponctuelles (congés) — F5.4.
     */
    public function unavailabilities(): HasMany
    {
        return $this->hasMany(ProviderUnavailability::class);
    }

    /**
     * Le prestataire est-il validé (donc autorisé à publier / recevoir des missions) ?
     */
    public function isValidated(): bool
    {
        return $this->status === ProviderStatus::VALIDE;
    }

    protected static function newFactory(): ProviderFactory
    {
        return ProviderFactory::new();
    }
}
