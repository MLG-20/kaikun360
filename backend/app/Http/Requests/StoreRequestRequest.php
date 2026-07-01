<?php

namespace App\Http\Requests;

use App\Enums\RequestPriority;
use App\Enums\ServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de la création d'une demande générique (POST /api/v1/requests).
 */
class StoreRequestRequest extends FormRequest
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
            'service_type' => ['required', Rule::in(ServiceType::values())],
            'message' => ['required', 'string', 'max:2000'],
            'budget_xof' => ['nullable', 'integer', 'min:0'],
            'city' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', Rule::in(RequestPriority::values())],
        ];
    }
}
