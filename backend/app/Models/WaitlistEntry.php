<?php

namespace App\Models;

use App\Enums\WaitlistCategory;
use App\Enums\WaitlistEntryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inscription à la liste d'attente avant ouverture officielle (2026-08-14).
 *
 * Émetteur non authentifié : on stocke son `name`/`phone` directement, comme
 * `ContactMessage`. `details` porte les champs propres à sa `category`.
 */
class WaitlistEntry extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
        'city',
        'category',
        'details',
        'precisions',
        'status',
        'handled_by',
        'handled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => WaitlistCategory::class,
            'details' => 'array',
            'status' => WaitlistEntryStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    /**
     * Agent/admin ayant traité l'inscription (null tant que non traitée).
     */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * Inscriptions encore à traiter.
     */
    public function scopeNouveau(Builder $query): Builder
    {
        return $query->where('status', WaitlistEntryStatus::NOUVEAU->value);
    }
}
