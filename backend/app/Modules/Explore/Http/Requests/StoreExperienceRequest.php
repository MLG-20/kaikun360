<?php

namespace App\Modules\Explore\Http\Requests;

use App\Modules\Explore\Models\TourismExperience;
use App\Rules\GoogleMapsLink;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la publication d'une expérience (POST /api/v1/experiences).
 *
 * L'autorisation passe par la policy `create` (prestataire vérifié, ou
 * super_admin via `Gate::before` — F21, dépôt de circuit par l'équipe).
 *
 * Un circuit doit naître avec AU MOINS une date de départ : sans ça, il ne
 * serait jamais réservable (F21).
 */
class StoreExperienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TourismExperience::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            // Programme jour par jour (F21) : [{day, title, description}, ...].
            'itinerary' => ['nullable', 'array'],
            'itinerary.*.day' => ['required', 'integer', 'min:1'],
            'itinerary.*.title' => ['nullable', 'string', 'max:255'],
            'itinerary.*.description' => ['nullable', 'string'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'price_xof' => ['required', 'integer', 'min:0'],
            // Ce qui est compris / non compris, en texte libre (F21).
            'included' => ['nullable', 'string'],
            'excluded' => ['nullable', 'string'],
            // Lien Google Maps collé par le prestataire (F5.10).
            'maps_link' => ['nullable', 'string', 'max:2048', 'url', new GoogleMapsLink()],
            // Dates de départ (F21) : chacune avec ses propres places.
            'departures' => ['required', 'array', 'min:1'],
            'departures.*.start_date' => ['required', 'date', 'after_or_equal:today'],
            'departures.*.seats_total' => ['required', 'integer', 'min:1'],
        ];
    }
}
