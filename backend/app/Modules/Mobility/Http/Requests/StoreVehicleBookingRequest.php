<?php

namespace App\Modules\Mobility\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation d'une location de véhicule (POST /api/v1/vehicles/{id}/bookings).
 */
class StoreVehicleBookingRequest extends FormRequest
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
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'guests' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
