<?php

namespace App\Models;

use App\Modules\Build\Enums\ReportType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Rapport de suivi (modèle transversal, introduit en B5.2).
 *
 * Polymorphe : `reportable` peut être une demande de construction
 * (`ConstructionRequest`, module Build) ou, plus tard, un projet diaspora (B8).
 * Porte des photos (liste de chemins) et/ou une vidéo (URL), un commentaire et
 * la date du constat de chantier.
 */
class Report extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'reportable_type',
        'reportable_id',
        'created_by',
        'type',
        'photos',
        'video_url',
        'comment',
        'reported_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ReportType::class,
            'photos' => 'array',
            'reported_at' => 'date',
        ];
    }

    /**
     * La cible du rapport (ConstructionRequest, projet diaspora…).
     */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * L'auteur du rapport (agent de suivi), facultatif.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
