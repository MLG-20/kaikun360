<?php

namespace App\Modules\TeamBuilding\Http\Requests;

use App\Modules\TeamBuilding\Models\TeamBuildingRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation du dépôt d'une demande de team building
 * (POST /api/v1/team-building-requests).
 */
class StoreTeamBuildingRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TeamBuildingRequest::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'participants' => ['required', 'integer', 'min:1'],
            'city' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'budget_xof' => ['nullable', 'integer', 'min:0'],
            'needs' => ['nullable', 'array'],
            'description' => ['nullable', 'string'],
        ];
    }
}
