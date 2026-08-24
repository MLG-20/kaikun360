<?php

namespace App\Modules\Immo\Http\Requests;

use App\Modules\Immo\Enums\PropertyType;
use App\Modules\Immo\Models\Property;
use App\Rules\GoogleMapsLink;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de la mise à jour d'un bien (PATCH /api/v1/properties/{property}).
 *
 * Mise à jour PARTIELLE (sometimes). Le STATUT n'est volontairement pas
 * modifiable ici : seul le circuit de validation (agents/admin, phase B2.4)
 * peut publier/suspendre un bien.
 */
class UpdatePropertyRequest extends FormRequest
{
    /**
     * Autorisé seulement au propriétaire du bien (ou admin) — via la policy.
     */
    public function authorize(): bool
    {
        $property = $this->route('property');

        return $property instanceof Property
            && ($this->user()?->can('update', $property) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Pour la cohérence géo, on se base sur la valeur fournie, sinon sur
        // celle déjà enregistrée sur le bien.
        $property = $this->route('property');
        $regionId = $this->input('region_id', $property?->region_id);
        $departmentId = $this->input('department_id', $property?->department_id);

        return [
            'type' => ['sometimes', Rule::in(PropertyType::values())],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price_xof' => ['sometimes', 'nullable', 'integer', 'min:0'],
            // Caution pour une location au mois (F5.8) — cf. StorePropertyRequest.
            'caution_xof' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'caution_months' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:12'],

            'region_id' => ['sometimes', 'required', 'integer', 'exists:regions,id'],
            'department_id' => [
                'sometimes', 'required', 'integer',
                Rule::exists('departments', 'id')->where('region_id', $regionId),
            ],
            'commune_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('communes', 'id')->where('department_id', $departmentId),
            ],

            'tourist_zone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'maps_link' => ['sometimes', 'nullable', 'string', 'max:2048', 'url', new GoogleMapsLink()],
        ];
    }
}
