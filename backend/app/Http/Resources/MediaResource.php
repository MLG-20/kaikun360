<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un média (couche transversale).
 *
 * @mixin \App\Models\Media
 */
class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'url' => $this->resolveUrl(),
            'is_primary' => $this->is_primary,
            'position' => $this->position,
            'status' => $this->status?->value,
            'original_name' => $this->original_name,
            'size_bytes' => $this->size_bytes,
        ];
    }
}
