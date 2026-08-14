<?php

namespace App\Http\Requests;

use App\Enums\WaitlistCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Inscription à la liste d'attente avant ouverture (2026-08-14).
 *
 * Ouvert à tous (pas d'authentification), même logique que
 * `StoreContactMessageRequest` : un prospect sans compte doit pouvoir
 * s'inscrire. La limitation de débit est appliquée sur la route.
 *
 * Les champs de `details` dépendent de la `category` choisie — `required_if`
 * les rend obligatoires seulement pour la bonne catégorie, sans jamais bloquer
 * les autres.
 */
class StoreWaitlistEntryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'category' => ['required', Rule::in(WaitlistCategory::values())],
            'precisions' => ['nullable', 'string', 'max:2000'],

            'details' => ['nullable', 'array'],

            'details.type_bien' => [
                Rule::requiredIf($this->input('category') === WaitlistCategory::PROPRIETAIRE->value),
                'nullable', 'string', Rule::in(['villa', 'appartement', 'terrain', 'commerce', 'autre']),
            ],
            'details.nb_biens' => ['nullable', 'integer', 'min:1'],

            'details.type_service' => [
                Rule::requiredIf($this->input('category') === WaitlistCategory::PRESTATAIRE->value),
                'nullable', 'string', Rule::in(['mobilite', 'tourisme', 'btp', 'team_building', 'autre']),
            ],

            'details.univers' => [
                Rule::requiredIf($this->input('category') === WaitlistCategory::CLIENT->value),
                'nullable', 'string', Rule::in(['immobilier', 'sejours', 'transport', 'services']),
            ],

            'details.taille_equipe' => [
                Rule::requiredIf($this->input('category') === WaitlistCategory::TEAM_BUILDING->value),
                'nullable', 'integer', 'min:1',
            ],
            'details.budget_xof' => ['nullable', 'integer', 'min:0'],

            'details.pays_residence' => [
                Rule::requiredIf($this->input('category') === WaitlistCategory::DIASPORA->value),
                'nullable', 'string', 'max:100',
            ],
            'details.type_projet' => [
                Rule::requiredIf($this->input('category') === WaitlistCategory::DIASPORA->value),
                'nullable', 'string', Rule::in(['achat', 'gestion_locative', 'construction']),
            ],
        ];
    }
}
