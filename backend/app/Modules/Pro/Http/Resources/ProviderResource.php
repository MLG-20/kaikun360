<?php

namespace App\Modules\Pro\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un prestataire marketplace (module Pro).
 *
 * @mixin \App\Modules\Pro\Models\Provider
 */
class ProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_name' => $this->business_name,
            'category' => $this->category,
            'category_label' => $this->categoryRef?->label ?? $this->category,
            'bio' => $this->bio,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'validated_at' => $this->validated_at?->toIso8601String(),
            'warnings_count' => $this->warnings_count,
            'sanction_note' => $this->sanction_note,
            'rating_avg' => $this->rating_avg,
            'rating_count' => $this->rating_count,
            'certifications' => ProviderCertificationResource::collection($this->whenLoaded('certifications')),
        ];
    }
}
