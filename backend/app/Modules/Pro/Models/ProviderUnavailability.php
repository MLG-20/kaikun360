<?php

namespace App\Modules\Pro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une période d'indisponibilité ponctuelle d'un prestataire (module Pro, F5.4).
 *
 * Congé / absence sur une plage de dates (incluses), avec un motif facultatif.
 * Ces périodes priment sur le planning hebdomadaire ([ProviderWeeklyAvailability]).
 */
class ProviderUnavailability extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider_id',
        'start_date',
        'end_date',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
