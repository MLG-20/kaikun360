<?php

namespace App\Http\Requests;

use App\Enums\RequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation d'un changement de statut de demande
 * (PATCH /api/v1/requests/{request}/status).
 *
 * L'autorisation (agents/admin) est portée par le middleware `can:traiter:demandes`.
 * La validité de la TRANSITION (machine à états) est vérifiée dans le contrôleur.
 */
class UpdateRequestStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(RequestStatus::values())],
        ];
    }
}
