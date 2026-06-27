<?php

namespace App\Modules\Manage\Models;

use App\Models\User;
use App\Modules\Immo\Models\Property;
use App\Modules\Manage\Enums\MandateStatus;
use Database\Factories\ManagementMandateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mandat de gestion locative (module Manage).
 *
 * Lie un bien et son propriétaire à Kaikun, avec un taux de commission.
 */
class ManagementMandate extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'property_id',
        'owner_id',
        'commission_rate',
        'start_date',
        'end_date',
        'status',
        'terms',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => MandateStatus::class,
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Les échéances de loyer du mandat.
     */
    public function rents(): HasMany
    {
        return $this->hasMany(Rent::class, 'mandate_id');
    }

    /**
     * Les reversements au propriétaire effectués sous ce mandat.
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(OwnerPayout::class, 'mandate_id');
    }

    /**
     * Les incidents du bien géré.
     *
     * Incidents et dépenses pointent vers le BIEN (`property_id`), pas vers le
     * mandat ; comme un mandat ne concerne qu'un bien, on relie via la colonne
     * partagée `property_id` (clé locale = clé étrangère = property_id).
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'property_id', 'property_id');
    }

    /**
     * Les dépenses du bien géré (même principe que les incidents).
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'property_id', 'property_id');
    }

    protected static function newFactory(): ManagementMandateFactory
    {
        return ManagementMandateFactory::new();
    }
}
