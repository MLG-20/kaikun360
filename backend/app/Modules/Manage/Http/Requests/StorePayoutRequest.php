<?php

namespace App\Modules\Manage\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la création d'un reversement au propriétaire
 * (POST .../mandates/{mandate}/payouts).
 *
 * L'`owner_id` est déduit du mandat côté contrôleur. Le reversement est créé
 * « en attente » ; il passe à « effectué » via l'action dédiée.
 */
class StorePayoutRequest extends FormRequest
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
            'period_label' => ['nullable', 'string', 'max:255'],
            'amount_xof' => ['required', 'integer', 'min:0'],
        ];
    }
}
