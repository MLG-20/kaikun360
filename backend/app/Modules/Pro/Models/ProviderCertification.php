<?php

namespace App\Modules\Pro\Models;

use Database\Factories\ProviderCertificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Document de certification d'un prestataire (module Pro).
 */
class ProviderCertification extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider_id',
        'name',
        'issuer',
        'file_path',
        'verified',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    protected static function newFactory(): ProviderCertificationFactory
    {
        return ProviderCertificationFactory::new();
    }
}
