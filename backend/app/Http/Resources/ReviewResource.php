<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un avis (couche transversale).
 *
 * @mixin \App\Models\Review
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author->id,
                'name' => $this->author->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
