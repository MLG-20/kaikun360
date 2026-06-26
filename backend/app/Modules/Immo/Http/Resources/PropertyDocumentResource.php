<?php

namespace App\Modules\Immo\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * Représentation JSON d'un document de bien.
 *
 * Le chemin de stockage n'est jamais exposé : seul un lien de téléchargement
 * SIGNÉ et TEMPORAIRE (10 min) est fourni.
 *
 * @mixin \App\Modules\Immo\Models\PropertyDocument
 */
class PropertyDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'validation_status' => $this->validation_status,
            'download_url' => URL::temporarySignedRoute(
                'properties.documents.download',
                now()->addMinutes(10),
                ['property' => $this->property_id, 'document' => $this->id],
            ),
            'created_at' => $this->created_at,
        ];
    }
}
