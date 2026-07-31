<?php

namespace App\Modules\Pro\Models;

use Database\Factories\ProviderCertificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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
        'disk',
        'original_name',
        'mime_type',
        'size',
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

    /**
     * Un justificatif a-t-il été joint ?
     *
     * Le fichier reste FACULTATIF : un prestataire peut déclarer sa
     * certification puis revenir déposer le scan. L'interface et le back-office
     * doivent donc distinguer « pas de pièce » de « pièce à contrôler », d'où ce
     * drapeau plutôt qu'un test sur le chemin dispersé dans chaque appelant.
     */
    public function hasFile(): bool
    {
        return $this->file_path !== null;
    }

    /**
     * Supprime le justificatif du disque, s'il existe.
     *
     * Appelé à la suppression de la certification : sans ça, le scan resterait
     * indéfiniment sur le disque privé alors que plus rien ne le référence —
     * de la donnée personnelle conservée sans motif.
     */
    public function deleteFile(): void
    {
        if ($this->file_path !== null) {
            Storage::disk($this->disk ?? 'local')->delete($this->file_path);
        }
    }

    protected static function newFactory(): ProviderCertificationFactory
    {
        return ProviderCertificationFactory::new();
    }
}
