<?php

namespace App\Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Code de vérification / réinitialisation à usage unique.
 *
 * Un code est "valide" tant qu'il n'a pas été consommé ni expiré.
 */
class VerificationCode extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'purpose',
        'channel',
        'code_hash',
        'failed_attempts',
        'expires_at',
        'consumed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope : uniquement les codes encore valides (non consommés, non expirés).
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('consumed_at')
            ->where('expires_at', '>', now());
    }
}
