<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'une inscription à la liste d'attente (2026-08-14).
 *
 * @mixin \App\Models\WaitlistEntry
 */
class WaitlistEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'city' => $this->city,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'details' => $this->details,
            'precisions' => $this->precisions,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'created_at' => $this->created_at,
        ];
    }
}
