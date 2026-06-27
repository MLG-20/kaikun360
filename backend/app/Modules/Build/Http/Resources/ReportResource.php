<?php

namespace App\Modules\Build\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un rapport de suivi (module Build).
 *
 * @mixin \App\Models\Report
 */
class ReportResource extends JsonResource
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
            'photos' => $this->photos ?? [],
            'video_url' => $this->video_url,
            'comment' => $this->comment,
            'reported_at' => $this->reported_at?->toDateString(),
        ];
    }
}
