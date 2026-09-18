<?php

namespace App\Modules\Admin\Http\Resources;

use App\Modules\Explore\Http\Resources\ExperienceDepartureResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un circuit touristique pour la **supervision
 * back-office** (F7.2.k).
 *
 * Complète `ExperienceResource` (public) avec ce dont l'équipe a besoin pour
 * piloter l'offre : le **remplissage** du circuit (places prises / restantes)
 * et le **prestataire** opérateur.
 *
 * ⚠️ Revu en F21 : un circuit a maintenant plusieurs dates de départ, chacune
 * avec ses propres places — `capacity_total`/`seats_taken` ci-dessous sont
 * des CUMULS toutes dates confondues (utiles pour un coup d'œil sur la
 * liste), le détail par date vit dans `departures`.
 *
 * `capacity_total` est agrégé par le contrôleur (`withSum('departures as
 * capacity_total', 'seats_total')`), `seats_taken` par (`withSum('bookings as
 * seats_taken', 'guests')`) — on ne les recalcule pas ici pour éviter une
 * requête par ligne.
 *
 * @mixin \App\Modules\Explore\Models\TourismExperience
 */
class AdminExperienceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $taken = (int) ($this->seats_taken ?? 0);
        $capacity = (int) ($this->capacity_total ?? 0);

        return [
            // --- Socle identique au catalogue public (l'écran Catalogues
            // F7.2.b consomme la même route : le format reste un sur-ensemble).
            'id' => $this->id,
            'reference' => $this->reference,
            'title' => $this->title,
            'destination' => $this->destination,
            'description' => $this->description,
            'itinerary' => $this->itinerary ?? [],
            'duration_days' => $this->duration_days,
            'price_xof' => $this->price_xof,
            'included' => $this->included,
            'excluded' => $this->excluded,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'published_at' => $this->published_at?->toIso8601String(),

            // --- Dates de départ, détaillées (chargées à la demande — pas sur
            // la liste, pour éviter un N+1 sur chaque ligne).
            'departures' => ExperienceDepartureResource::collection($this->whenLoaded('departures')),

            // --- Remplissage (supervision), cumulé toutes dates confondues.
            'capacity_total' => $capacity,
            'seats_taken' => $taken,
            'seats_left' => max(0, $capacity - $taken),

            // --- Médias (F8.1) : compteurs de supervision, présents seulement
            // quand la requête les a agrégés (`withCount`). Permettent de
            // repérer une annonce publiée sans visuel, ou dont des photos ont
            // été masquées par la modération.
            'media_count' => $this->when(
                $this->media_count !== null,
                fn () => (int) $this->media_count,
            ),
            'media_hidden_count' => $this->when(
                $this->media_hidden_count !== null,
                fn () => (int) $this->media_hidden_count,
            ),

            // --- Prestataire opérateur (pour joindre en cas d'anomalie).
            'provider' => $this->whenLoaded('provider', fn () => [
                'id' => $this->provider->id,
                'name' => $this->provider->name,
                'email' => $this->provider->email,
                'phone' => $this->provider->phone,
            ]),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
