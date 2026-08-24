<?php

namespace App\Modules\Immo\Http\Requests;

use App\Modules\Immo\Enums\PropertyType;
use App\Modules\Immo\Models\Property;
use App\Rules\GoogleMapsLink;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation du dépôt d'un bien (POST /api/v1/properties).
 *
 * Vérifie aussi la COHÉRENCE géographique : le département doit appartenir à la
 * région indiquée, et la commune au département. Impossible donc d'enregistrer
 * un bien avec une localisation incohérente.
 */
class StorePropertyRequest extends FormRequest
{
    /**
     * Seuls les rôles autorisés (propriétaire/admin) peuvent déposer un bien.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Property::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(PropertyType::values())],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price_xof' => ['nullable', 'integer', 'min:0'],
            // Caution pour une location au mois (F5.8) : le propriétaire déclare
            // un montant MENSUEL (`caution_xof`) et un nombre de mois
            // (`caution_months`) ; le TOTAL se calcule automatiquement
            // (`Property::caution_total_xof`). Sans lien avec la caution des
            // nuitées (`stays.caution_xof`).
            'caution_xof' => ['nullable', 'integer', 'min:0'],
            'caution_months' => ['nullable', 'integer', 'min:1', 'max:12'],

            // Région obligatoire (référentiel).
            'region_id' => ['required', 'integer', 'exists:regions,id'],
            // Le département doit exister ET appartenir à la région indiquée.
            'department_id' => [
                'required', 'integer',
                Rule::exists('departments', 'id')->where('region_id', $this->input('region_id')),
            ],
            // La commune (facultative) doit appartenir au département indiqué.
            'commune_id' => [
                'nullable', 'integer',
                Rule::exists('communes', 'id')->where('department_id', $this->input('department_id')),
            ],

            'tourist_zone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            // Lien Google Maps collé par le propriétaire (F5.10).
            'maps_link' => ['nullable', 'string', 'max:2048', 'url', new GoogleMapsLink()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Le type de bien est obligatoire.',
            'type.in' => 'Le type de bien est invalide.',
            'title.required' => 'Le titre est obligatoire.',
            'region_id.required' => 'La région est obligatoire.',
            'region_id.exists' => 'La région sélectionnée est invalide.',
            'department_id.required' => 'Le département est obligatoire.',
            'department_id.exists' => "Le département est invalide ou n'appartient pas à la région.",
            'commune_id.exists' => "La commune est invalide ou n'appartient pas au département.",
        ];
    }
}
