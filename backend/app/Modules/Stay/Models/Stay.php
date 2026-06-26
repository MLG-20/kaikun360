<?php

namespace App\Modules\Stay\Models;

use App\Models\Booking;
use App\Modules\Immo\Enums\PropertyStatus;
use App\Modules\Immo\Models\Property;
use Database\Factories\StayFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Configuration "nuitées" d'un bien (module Stay).
 *
 * Un Stay est visible publiquement si son bien est publié ET qu'il est actif.
 */
class Stay extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'property_id',
        'price_per_night_xof',
        'caution_xof',
        'capacity',
        'min_nights',
        'max_nights',
        'rules',
        'amenities',
        'check_in_time',
        'check_out_time',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_per_night_xof' => 'integer',
            'caution_xof' => 'integer',
            'capacity' => 'integer',
            'min_nights' => 'integer',
            'max_nights' => 'integer',
            'rules' => 'array',
            'amenities' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Le bien immobilier sous-jacent.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Les réservations de cette nuitée (relation polymorphe).
     */
    public function bookings(): MorphMany
    {
        return $this->morphMany(Booking::class, 'bookable');
    }

    /**
     * Scope : nuitées réellement réservables (actives + bien publié).
     */
    public function scopeBookable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereHas('property', fn (Builder $q) => $q->where('status', PropertyStatus::PUBLIE->value));
    }

    protected static function newFactory(): StayFactory
    {
        return StayFactory::new();
    }
}
