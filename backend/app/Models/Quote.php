<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Devis générique rattaché à une demande (couche transversale, B11.3).
 */
class Quote extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'request_id',
        'agent_id',
        'amount_xof',
        'details',
        'valid_until',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_xof' => 'integer',
            'details' => 'array',
            'valid_until' => 'date',
            'status' => QuoteStatus::class,
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }

    /**
     * L'agent qui a composé le devis (F8.11).
     *
     * Nullable : les devis antérieurs à cette phase n'ont pas d'auteur connu.
     * C'est cet agent — nommé — que le client voit comme interlocuteur, et c'est
     * lui seul qu'on prévient quand le devis est tranché.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * La réservation née de l'acceptation de ce devis (F8.11).
     *
     * ⚠️ Le devis est LUI-MÊME la cible polymorphe de la réservation
     * (`bookable_type = Quote`). Ce n'est pas un pis-aller : le sur-mesure n'a
     * pas de fiche au catalogue, et l'objet vendu est précisément le devis —
     * son montant, ses lignes, sa validité. Aucune migration de `bookings` n'a
     * donc été nécessaire, la table étant polymorphe depuis B3.3.
     *
     * `morphOne` et non `morphMany` : un devis ne se convertit qu'une fois, la
     * conversion étant idempotente (cf. QuoteController::respond).
     */
    public function booking(): MorphOne
    {
        return $this->morphOne(Booking::class, 'bookable');
    }
}
