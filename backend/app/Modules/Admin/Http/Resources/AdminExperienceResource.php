<?php

namespace App\Modules\Admin\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un circuit touristique pour la **supervision
 * back-office** (F7.2.k).
 *
 * Complète `ExperienceResource` (public) avec ce dont l'équipe a besoin pour
 * piloter l'offre : le **remplissage** du circuit (places prises / restantes —
 * les « capacités groupes » du cahier des charges) et le **prestataire**
 * opérateur.
 *
 * ⚠️ Rappel métier (B6.3) : une expérience n'a **pas de date de départ**, sa
 * capacité est un **total par circuit** — le remplissage est donc cumulé sur
 * toutes ses réservations non annulées, pas sur une session datée.
 *
 * `seats_taken` est agrégé par le contrôleur (`withSum`) et déposé sur le
 * modèle ; on ne le recalcule pas ici pour éviter une requête par ligne.
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
        $capacity = (int) $this->capacity;

        return [
            // --- Socle identique au catalogue public (l'écran Catalogues
            // F7.2.b consomme la même route : le format reste un sur-ensemble).
            'id' => $this->id,
            'reference' => $this->reference,
            'title' => $this->title,
            'destination' => $this->destination,
            'description' => $this->description,
            'duration_days' => $this->duration_days,
            'price_xof' => $this->price_xof,
            'capacity' => $capacity,
            // Le « programme » du circuit au sens du cahier des charges :
            // ce que la prestation inclut (restauration, guide, transport…).
            'inclusions' => $this->inclusions ?? [],
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'published_at' => $this->published_at?->toIso8601String(),

            // --- Remplissage (supervision).
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
