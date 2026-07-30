<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Initiation d'un paiement pour une réservation (B14.2).
 */
class InitiatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            // `paytech` (défaut) ou `manuel` (Phase 1 du cahier des charges).
            'mode' => ['sometimes', 'in:paytech,manuel'],
            // F7.3.h — ACOMPTE : montant partiel. Omis, le client règle tout ce
            // qu'il reste à payer. Le plafond (reste dû) est vérifié dans le
            // contrôleur, qui seul connaît la réservation visée.
            'amount_xof' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
