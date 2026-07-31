<?php

namespace App\Modules\Pro\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * Représentation JSON d'une certification prestataire (module Pro).
 *
 * Le chemin de stockage n'est JAMAIS exposé : le justificatif vit sur le disque
 * privé et ne se lit que par URL signée temporaire (même règle que les pièces
 * KYC, cf. `UserDocumentResource`).
 *
 * @mixin \App\Modules\Pro\Models\ProviderCertification
 */
class ProviderCertificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'issuer' => $this->issuer,
            'verified' => $this->verified,

            // — Justificatif (F8.0). Facultatif : `has_file` à false signifie
            // « déclarée, pièce non fournie », un état légitime que l'interface
            // doit savoir montrer.
            'has_file' => $this->hasFile(),
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            // URL signée valable 10 minutes, comme pour le KYC. `null` quand il
            // n'y a pas de pièce : pas de lien qui mènerait à un 404.
            'download_url' => $this->hasFile()
                ? URL::temporarySignedRoute(
                    'providers.certifications.download',
                    now()->addMinutes(10),
                    ['certification' => $this->id],
                )
                : null,
        ];
    }
}
