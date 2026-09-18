<?php

namespace App\Modules\Explore\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation d'une réservation d'expérience (POST /api/v1/experiences/{id}/bookings).
 *
 * `guests` = nombre de participants (panier groupe). `departure_id` = la date
 * de départ choisie parmi celles du circuit (F21) — remplace l'ancienne date
 * libre : les dates de départ sont désormais fixées par le prestataire/admin,
 * pas inventées par le client au moment de réserver.
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
            'departure_id' => [
                'required',
                'integer',
                Rule::exists('tourism_experience_departures', 'id')
                    ->where('tourism_experience_id', $this->route('id')),
            ],
        ];
    }
}
