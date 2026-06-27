<?php

namespace App\Modules\Manage\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la création d'une échéance de loyer (POST .../mandates/{mandate}/rents).
 */
class StoreRentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gerer:gestion-locative') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['nullable', 'integer', 'exists:users,id'],
            'tenant_name' => ['nullable', 'string', 'max:255'],
            'period_label' => ['nullable', 'string', 'max:255'],
            'due_date' => ['required', 'date'],
            'amount_xof' => ['required', 'integer', 'min:0'],
        ];
    }
}
