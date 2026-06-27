<?php

namespace App\Modules\Build\Http\Requests;

use App\Modules\Build\Enums\ReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de la publication d'un rapport de suivi par un agent
 * (POST /api/v1/construction-requests/{id}/reports).
 *
 * Réservé à la permission `gerer:chantiers` (middleware `can:` sur la route).
 */
class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gerer:chantiers') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(ReportType::values())],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['string', 'max:2048'],
            'video_url' => ['nullable', 'url', 'max:2048'],
            'comment' => ['nullable', 'string'],
            'reported_at' => ['required', 'date'],
        ];
    }
}
