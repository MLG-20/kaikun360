<?php

namespace App\Modules\TeamBuilding\Models;

use App\Models\User;
use App\Modules\TeamBuilding\Enums\TeamBuildingRequestStatus;
use Database\Factories\TeamBuildingRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Demande de team building d'une entreprise (module Team Building).
 *
 * Appartient à une entreprise (User) et donnera lieu à un ou plusieurs devis
 * composés (TeamBuildingQuote, B9.2). La couche transversale Quotes (B11)
 * généralisera la notion de devis.
 */
class TeamBuildingRequest extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'company_id',
        'participants',
        'city',
        'start_date',
        'end_date',
        'budget_xof',
        'needs',
        'description',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'participants' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'budget_xof' => 'integer',
            'needs' => 'array',
            'status' => TeamBuildingRequestStatus::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(User::class, 'company_id');
    }

    /**
     * Les devis composés pour cette demande (B9.2).
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(TeamBuildingQuote::class, 'request_id');
    }

    /**
     * Les prestataires affectés à cette demande (F7.2.h « affectation
     * prestataires » du CDC §6) — chaque affectation est une mission Pro rattachée.
     */
    public function providerMissions(): HasMany
    {
        return $this->hasMany(\App\Modules\Pro\Models\ProviderMission::class, 'team_building_request_id');
    }

    protected static function newFactory(): TeamBuildingRequestFactory
    {
        return TeamBuildingRequestFactory::new();
    }
}
