<?php

namespace App\Http\Requests;

use App\Enums\WaitlistEntryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour du statut d'une inscription à la liste d'attente par l'équipe (2026-08-14).
 * L'autorisation (`traiter:demandes`) est portée par la route admin.
 */
class UpdateWaitlistEntryRequest extends FormRequest
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
            'status' => ['required', Rule::enum(WaitlistEntryStatus::class)],
        ];
    }
}
