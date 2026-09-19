<?php

namespace App\Modules\Explore\Http\Resources;

use App\Support\Offers\KaikunPublisher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'une expérience touristique (module Explore).
 *
 * @mixin \App\Modules\Explore\Models\TourismExperience
 */
class ExperienceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            // Étiquette « Kaikun 360 » : offre déposée par l'équipe, pas par un
            // prestataire (voir KaikunPublisher).
            'published_by_kaikun' => KaikunPublisher::isKaikun($this->provider_id),
            'title' => $this->title,
            'destination' => $this->destination,
            'description' => $this->description,
            // Programme jour par jour (F21) : [{day, title, description}, ...].
            'itinerary' => $this->itinerary ?? [],
            'duration_days' => $this->duration_days,
            'price_xof' => $this->price_xof,
            'included' => $this->included,
            'excluded' => $this->excluded,
            // Lien Google Maps collé par le prestataire (F5.10).
            'maps_link' => $this->maps_link,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'published_at' => $this->published_at?->toIso8601String(),
            // Dates de départ (F21) : chacune avec ses propres places. `seats_left`
            // par date n'apparaît que si le contrôleur l'a calculé (cf.
            // ExperienceDepartureResource).
            'departures' => ExperienceDepartureResource::collection($this->whenLoaded('departures')),
            // F8.18 — LES PHOTOS. Même dette que les véhicules : `HasMedia` sur
            // le modèle, clé `experience` acceptée par `POST /media/upload`, et
            // aucun chemin de retour vers les écrans. Un circuit est pourtant ce
            // qui se vend le plus par l'image.
            //
            // ⚠️ `whenLoaded` : pas de N+1 sur le catalogue Tourisme.
            'photos' => \App\Http\Resources\MediaResource::collection($this->whenLoaded('media')),
            'photo_url' => $this->when(
                $this->relationLoaded('media'),
                fn () => $this->media->first()?->resolveUrl(),
            ),
        ];
    }
}
