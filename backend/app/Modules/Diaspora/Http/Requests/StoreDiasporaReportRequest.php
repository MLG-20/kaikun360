<?php

namespace App\Modules\Diaspora\Http\Requests;

use App\Modules\Build\Enums\ReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de l'ajout d'un rapport de suivi sur un projet diaspora
 * (POST /api/v1/diaspora-projects/{project}/reports).
 *
 * L'autorisation (agent affecté ou admin) est vérifiée dans le contrôleur via la
 * policy `update`. Réutilise l'enum `ReportType` du modèle transversal Report.
 */
class StoreDiasporaReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
