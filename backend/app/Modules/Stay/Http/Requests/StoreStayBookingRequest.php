<?php

namespace App\Modules\Stay\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation d'une demande de réservation de nuitée
 * (POST /api/v1/stays/{id}/bookings).
 *
 * Validation de base ici (dates, nombre de personnes). Les règles dépendant de
 * la nuitée (capacité, nuits min/max, chevauchement) sont vérifiées dans le
 * contrôleur, car elles nécessitent le Stay ciblé.
 */
class StoreStayBookingRequest extends FormRequest
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
            'end_date' => ['required', 'date', 'after:start_date'],
            'guests' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_date.required' => "La date d'arrivée est obligatoire.",
            'start_date.after_or_equal' => "La date d'arrivée ne peut pas être dans le passé.",
            'end_date.required' => 'La date de départ est obligatoire.',
            'end_date.after' => "La date de départ doit être postérieure à l'arrivée.",
            'guests.required' => 'Le nombre de personnes est obligatoire.',
            'guests.min' => 'Au moins une personne est requise.',
        ];
    }
}
