<?php

namespace App\Modules\Pro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un jour du planning hebdomadaire récurrent d'un prestataire (module Pro, F5.4).
 *
 * `weekday` : 0 = lundi … 6 = dimanche. Le jour est ouvert (`is_open`) avec une
 * plage horaire, ou fermé. Voir aussi [ProviderUnavailability] pour les absences
 * ponctuelles qui priment sur ce planning.
 */
class ProviderWeeklyAvailability extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider_id',
        'weekday',
        'is_open',
        'start_time',
        'end_time',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'is_open' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
