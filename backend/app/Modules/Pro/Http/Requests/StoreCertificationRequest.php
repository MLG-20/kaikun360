<?php

namespace App\Modules\Pro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de l'ajout d'un document de certification
 * (POST /api/v1/providers/certifications).
 *
 * Une certification ajoutée est TOUJOURS « non vérifiée » : la vérification est
 * une action back-office (le contrôleur n'accepte donc pas `verified`).
 */
class StoreCertificationRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'issuer' => ['nullable', 'string', 'max:255'],
        ];
    }
}
