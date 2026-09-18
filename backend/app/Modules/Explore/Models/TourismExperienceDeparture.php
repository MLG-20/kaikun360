<?php

namespace App\Modules\Explore\Models;

use Database\Factories\TourismExperienceDepartureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une date de départ d'un circuit, avec ses propres places (F21).
 *
 * ⚠️ Sans lien vers `bookings` : voir la migration pour pourquoi une
 * réservation ne référence jamais une ligne de cette table par clé étrangère.
 */
class TourismExperienceDeparture extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tourism_experience_id',
        'start_date',
        'seats_total',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'seats_total' => 'integer',
        ];
    }

    public function experience(): BelongsTo
    {
        return $this->belongsTo(TourismExperience::class, 'tourism_experience_id');
    }

    protected static function newFactory(): TourismExperienceDepartureFactory
    {
        return TourismExperienceDepartureFactory::new();
    }
}
