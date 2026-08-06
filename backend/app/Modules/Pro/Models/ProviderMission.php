<?php

namespace App\Modules\Pro\Models;

use App\Models\User;
use App\Modules\Build\Models\ConstructionRequest;
use App\Modules\Pro\Enums\MissionStatus;
use App\Modules\TeamBuilding\Models\TeamBuildingRequest;
use Database\Factories\ProviderMissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Mission affectée à un prestataire (module Pro).
 */
class ProviderMission extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'provider_id',
        'client_id',
        'team_building_request_id',
        'construction_request_id',
        'category',
        'title',
        'description',
        'amount_xof',
        'commission_xof',
        'status',
        'scheduled_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_xof' => 'integer',
            'commission_xof' => 'integer',
            'status' => MissionStatus::class,
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * La dette envers le prestataire née de cette mission (F8.16.a).
     *
     * ⚠️ **C'est par ici que team building et construction sont reversés** : le
     * devis de ces univers est un total « coûts + marge » qui ne dit rien de ce
     * qui revient à chaque intervenant. Ce qui est dû vit mission par mission.
     */
    public function partnerDue(): MorphOne
    {
        return $this->morphOne(\App\Models\PartnerDue::class, 'source');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Demande de team building d'origine (null pour une mission ordinaire).
     */
    public function teamBuildingRequest(): BelongsTo
    {
        return $this->belongsTo(TeamBuildingRequest::class);
    }

    /**
     * Chantier de construction d'origine (null pour une mission ordinaire) — F7.3.e3.
     *
     * ⚠️ `category` porte alors un **lot BTP** (`ConstructionLot`), là où elle
     * porte une brique de pack pour une mission team building : c'est la clé
     * étrangère renseignée qui dit quel vocabulaire lire.
     */
    public function constructionRequest(): BelongsTo
    {
        return $this->belongsTo(ConstructionRequest::class);
    }

    protected static function newFactory(): ProviderMissionFactory
    {
        return ProviderMissionFactory::new();
    }
}
