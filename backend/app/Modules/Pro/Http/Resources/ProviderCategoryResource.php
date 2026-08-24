<?php

namespace App\Modules\Pro\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'une catégorie de service prestataire.
 *
 * @mixin \App\Modules\Pro\Models\ProviderCategory
 */
class ProviderCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
        ];
    }
}
