<?php

namespace App\Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * Représentation JSON d'une pièce justificative.
 *
 * Le chemin de stockage réel n'est JAMAIS exposé. On fournit à la place une
 * URL de téléchargement SIGNÉE et TEMPORAIRE (valable 10 minutes), seule façon
 * d'accéder au fichier privé.
 *
 * @mixin \App\Modules\Core\Models\UserDocument
 */
class UserDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'status' => $this->status,
            'download_url' => URL::temporarySignedRoute(
                'users.documents.download',
                now()->addMinutes(10),
                ['document' => $this->id],
            ),
            'created_at' => $this->created_at,
        ];
    }
}
