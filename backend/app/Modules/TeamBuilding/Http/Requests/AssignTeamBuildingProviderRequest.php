<?php

namespace App\Modules\TeamBuilding\Http\Requests;

use App\Modules\TeamBuilding\Enums\QuoteLineCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de l'affectation d'un prestataire à une demande de team building
 * (POST /api/v1/team-building-requests/{request}/assignments) — F7.2.h.
 *
 * L'autorisation (back-office) est vérifiée via la policy `manage` dans le
 * contrôleur. On affecte un prestataire VALIDÉ à une brique du pack (catégorie)
 * pour un montant convenu ; l'affectation devient une mission Pro rémunérée.
 */
class AssignTeamBuildingProviderRequest extends FormRequest
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
            'provider_id' => ['required', 'integer', 'exists:providers,id'],
            'category' => ['required', Rule::in(QuoteLineCategory::values())],
            'title' => ['nullable', 'string', 'max:255'],
            'amount_xof' => ['required', 'integer', 'min:0'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
