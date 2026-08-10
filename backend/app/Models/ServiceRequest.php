<?php

namespace App\Models;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Demande client générique (couche transversale, B11).
 *
 * Table `requests` ; nommé `ServiceRequest` pour ne pas entrer en conflit avec
 * `Illuminate\Http\Request`. Suit une machine à états stricte (RequestStatus) et
 * peut porter des devis (Quote).
 */
class ServiceRequest extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'requests';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'user_id',
        'service_type',
        'message',
        'budget_xof',
        'city',
        'status',
        'priority',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'budget_xof' => 'integer',
            'status' => RequestStatus::class,
            'priority' => RequestPriority::class,
            // F11.5 — rangée par le client dans sa corbeille. ⚠️ VOLONTAIREMENT
            // absente de `$fillable` : ce n'est pas une donnée de la demande,
            // c'est une préférence d'affichage. Elle ne s'écrit que par
            // `PersonalHiding`, jamais par un `create()`/`update()` de masse.
            'hidden_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Les devis rattachés à la demande (B11.3).
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class, 'request_id');
    }
}
