<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON PUBLIQUE d'une carte « Location de véhicules ».
 *
 * @mixin \App\Models\VehicleShowcaseCard
 */
class VehicleShowcaseCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'image' => $this->imageUrl(),
            'link_url' => $this->link_url,
            'link_label' => $this->link_label,
        ];
    }
}
