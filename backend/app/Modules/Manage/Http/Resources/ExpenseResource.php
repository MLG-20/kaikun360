<?php

namespace App\Modules\Manage\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'une dépense liée à un bien (module Manage).
 *
 * @mixin \App\Modules\Manage\Models\Expense
 */
class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'incident_id' => $this->incident_id,
            'label' => $this->label,
            'category' => $this->category?->value,
            'category_label' => $this->category?->label(),
            'amount_xof' => (int) $this->amount_xof,
            'spent_at' => $this->spent_at?->toDateString(),
        ];
    }
}
