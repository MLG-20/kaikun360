<?php

namespace App\Modules\Admin\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Session de présence d'un membre de l'équipe back-office (pointeuse, F7.1.c).
 *
 * Entrée (`started_at`) → sortie (`ended_at`). Tant que `ended_at` est nul, la
 * personne est réputée « en poste ». La durée n'est connue qu'une fois la sortie
 * pointée.
 */
class Attendance extends Model
{
    protected $fillable = [
        'user_id',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * L'employé auquel appartient cette session de présence.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Sessions encore ouvertes (entrée pointée, pas de sortie).
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    /**
     * La session est-elle en cours (personne actuellement en poste) ?
     */
    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * Durée de la session en minutes, ou `null` si elle est encore ouverte.
     */
    public function durationMinutes(): ?int
    {
        if ($this->ended_at === null) {
            return null;
        }

        return $this->started_at->diffInMinutes($this->ended_at);
    }
}
