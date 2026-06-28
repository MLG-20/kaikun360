<?php

namespace App\Modules\Explore\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation d'une réservation d'expérience (POST /api/v1/experiences/{id}/bookings).
 *
 * `guests` = nombre de participants (panier groupe). `start_date` = date de
 * départ choisie ; la date de fin est déduite de la durée du circuit.
 */
class StoreExperienceBookingRequest extends FormRequest
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
            'start_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }
}
