<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Conversation de la messagerie (couche transversale, F3.7).
 *
 * Socle GÉNÉRIQUE : un fil regroupe plusieurs participants (pivot
 * `conversation_user`) et une suite de messages (`messages`). Il est découplé
 * des rôles — n'importe quels utilisateurs peuvent converser — et réutilisable
 * tel quel par les espaces pro (F4/F5/F6).
 *
 * `context_type` / `context_id` : lien polymorphe FACULTATIF vers la ressource
 * à l'origine de l'échange (demande, réservation, bien…). On ne définit pas de
 * `morphTo` typé pour ne pas coupler ce modèle transversal aux modèles métier ;
 * le contexte sert surtout d'étiquette (« À propos de… ») côté affichage.
 */
class Conversation extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subject',
        'assigned_agent_id',
        'context_type',
        'context_id',
        'last_message_at',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Agent RESPONSABLE du fil (F8.12) — celui dont le client voit le nom et
     * qui doit la réponse. Il figure aussi parmi les `participants` : la
     * relation ci-dessous dit qui en répond, le pivot dit qui peut lire.
     */
    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    /**
     * Un fil clos est réglé : il quitte la file de traitement sans disparaître.
     */
    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /**
     * Participants du fil (N–N). Le pivot porte `last_read_at`, propre à chaque
     * participant, pour le calcul des messages non lus.
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            // `hidden_at` (F11.5) : ce participant a rangé le fil dans SA
            // corbeille. Sur le pivot, jamais sur le fil — voir `User::conversations`.
            ->withPivot('last_read_at', 'hidden_at')
            ->withTimestamps();
    }

    /**
     * Tous les messages du fil, du plus ancien au plus récent (ordre d'affichage).
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->oldest();
    }

    /**
     * Dernier message du fil (aperçu dans la liste des conversations).
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * PREMIER message du fil — donc celui qui l'a ouvert (F8.12.c).
     *
     * ⚠️ C'est la seule définition fiable du « demandeur ». Le back-office le
     * déduisait du premier participant non-staff : dès qu'un propriétaire ou un
     * prestataire entre dans le fil, cette règle désigne parfois le TIERS à la
     * place du client — la fiche affichait alors le mauvais nom et les
     * mauvaises coordonnées. L'auteur du premier message, lui, ne change jamais.
     */
    public function firstMessage(): HasOne
    {
        return $this->hasOne(Message::class)->oldestOfMany();
    }

    /**
     * Nombre de messages non lus pour un participant donné : messages postérieurs
     * à son `last_read_at` (ou tous, s'il n'a jamais lu) et qu'il n'a pas émis.
     */
    public function unreadCountFor(User $user): int
    {
        $lastReadAt = $this->participants
            ->firstWhere('id', $user->id)?->pivot?->last_read_at;

        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->when($lastReadAt, fn ($query) => $query->where('created_at', '>', $lastReadAt))
            ->count();
    }
}
