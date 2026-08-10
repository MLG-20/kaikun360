<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message d'une conversation (couche transversale, F3.7).
 *
 * Appartient à une conversation (`conversation_id`) et à son auteur
 * (`sender_id`). Le corps est du texte brut ; l'échappement se fait à
 * l'affichage côté frontend.
 */
class Message extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
    ];

    /**
     * Un message neuf FAIT REVENIR le fil chez ceux qui l'avaient rangé (F11.5).
     *
     * ⚠️ **Sans cette règle, la corbeille deviendrait un silencieux.** Un client
     * range un fil réglé, l'agent y répond trois jours plus tard : la réponse
     * arriverait dans un fil invisible, et personne ne saurait pourquoi le
     * client ne répond plus. Ranger dit « je n'ai plus rien à y faire », pas
     * « ne me parlez plus ».
     *
     * ⚠️ **Posé sur l'événement du modèle, et pas dans les contrôleurs, parce
     * qu'il y a QUATRE endroits qui créent un message** (espace client ×3,
     * back-office ×1) : une règle recopiée quatre fois est une règle qu'on
     * oubliera au cinquième. Ici, aucun chemin ne peut y échapper.
     *
     * L'auteur est exclu : il vient d'écrire, son propre message n'a pas à lui
     * ressortir un fil qu'il avait rangé.
     */
    protected static function booted(): void
    {
        static::created(function (Message $message): void {
            $fil = $message->conversation;

            if ($fil === null) {
                return;
            }

            $aPrevenir = $fil->participants()
                ->wherePivotNotNull('hidden_at')
                ->where('users.id', '!=', $message->sender_id)
                ->pluck('users.id');

            if ($aPrevenir->isNotEmpty()) {
                $fil->participants()->updateExistingPivot($aPrevenir->all(), ['hidden_at' => null]);
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Auteur du message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
