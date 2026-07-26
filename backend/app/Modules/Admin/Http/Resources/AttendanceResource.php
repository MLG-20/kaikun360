<?php

namespace App\Modules\Admin\Http\Resources;

use App\Modules\Admin\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'une session de présence (pointeuse, F7.1.c).
 *
 * @mixin Attendance
 */
class AttendanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'duration_minutes' => $this->durationMinutes(),
            'is_open' => $this->isOpen(),
        ];
    }
}
