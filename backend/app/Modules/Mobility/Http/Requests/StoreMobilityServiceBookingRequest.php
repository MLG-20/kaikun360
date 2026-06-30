<?php

namespace App\Modules\Mobility\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation d'une réservation de service de mobilité
 * (POST /api/v1/mobility-services/{id}/bookings).
 */
class StoreMobilityServiceBookingRequest extends FormRequest
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
            'guests' => ['required', 'integer', 'min:1'],
        ];
    }
}
