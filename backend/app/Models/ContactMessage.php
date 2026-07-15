<?php

namespace App\Models;

use App\Enums\ContactMessageStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message de contact envoyé depuis la page publique (F2.8.1).
 *
 * Émetteur non authentifié : on stocke son `name`/`email`. Traité par l'équipe
 * (permission `traiter:demandes`) qui bascule le `status` sur « traité » et
 * renseigne `handled_by`/`handled_at`.
 */
class ContactMessage extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
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
            'status' => ContactMessageStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    /**
     * Agent/admin ayant traité le message (null tant que non traité).
     */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * Messages encore à traiter.
     */
    public function scopeNouveau(Builder $query): Builder
    {
        return $query->where('status', ContactMessageStatus::NOUVEAU->value);
    }
}
