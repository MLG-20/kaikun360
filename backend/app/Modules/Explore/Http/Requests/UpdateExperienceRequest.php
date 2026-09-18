<?php

namespace App\Modules\Explore\Http\Requests;

use App\Rules\GoogleMapsLink;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de la mise à jour d'un circuit (PATCH /api/v1/experiences/{id}).
 *
 * Miroir de `StoreExperienceRequest`, en `sometimes` : une modification partielle
 * ne doit pas obliger à renvoyer tout le formulaire.
 *
 * L'autorisation (prestataire propriétaire ou admin) est vérifiée dans le
 * contrôleur via la policy `update` — comme `UpdateVehicleRequest`. Le statut
 * n'est pas modifiable ici : il évolue par la validation d'un agent.
 *
 * `departures.*.id`, quand il est présent, désigne une date EXISTANTE à
 * corriger (place restée à `null`, elle est créée) ; toute date déjà en base
 * mais absente du tableau envoyé est retirée par `ExperienceDepartureSyncer`
 * — sauf si des réservations actives s'y accrochent déjà.
 */
class UpdateExperienceRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'destination' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'itinerary' => ['sometimes', 'nullable', 'array'],
            'itinerary.*.day' => ['required', 'integer', 'min:1'],
            'itinerary.*.title' => ['nullable', 'string', 'max:255'],
            'itinerary.*.description' => ['nullable', 'string'],
            'duration_days' => ['sometimes', 'integer', 'min:1'],
            'price_xof' => ['sometimes', 'integer', 'min:0'],
            'included' => ['sometimes', 'nullable', 'string'],
            'excluded' => ['sometimes', 'nullable', 'string'],
            'maps_link' => ['sometimes', 'nullable', 'string', 'max:2048', 'url', new GoogleMapsLink()],
            'departures' => ['sometimes', 'array', 'min:1'],
            'departures.*.id' => [
                'sometimes',
                'integer',
                Rule::exists('tourism_experience_departures', 'id')
                    ->where('tourism_experience_id', $this->route('experience')?->id),
            ],
            'departures.*.start_date' => ['required', 'date'],
            'departures.*.seats_total' => ['required', 'integer', 'min:1'],
        ];
    }
}
