<?php

namespace App\Modules\Pro\Http\Resources;

use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Pro\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un **avis reçu** par un prestataire (« Avis reçus », F5.5).
 *
 * Étend l'avis transversal d'un libellé de **source** (`source`) qui indique
 * d'où provient l'avis — le véhicule ou l'expérience noté, ou une prestation
 * directe sur mission —, afin que le prestataire situe chaque avis. L'auteur et
 * la ressource notée (`reviewable`) sont supposés préchargés par le contrôleur.
 *
 * @mixin \App\Models\Review
 */
class ProviderReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'rating' => $this->rating,
            'comment' => $this->comment,
            // Libellé lisible de la provenance de l'avis.
            'source' => $this->sourceLabel(),
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author->id,
                'name' => $this->author->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Libellé de la source de l'avis d'après la ressource notée. « Prestation
     * directe » pour un avis sur le prestataire lui-même (mission), sinon le nom
     * du véhicule ou de l'expérience concerné.
     */
    private function sourceLabel(): string
    {
        $reviewable = $this->reviewable;

        return match (true) {
            $reviewable instanceof Provider => 'Prestation directe',
            $reviewable instanceof Vehicle => 'Véhicule · '.trim($reviewable->brand.' '.$reviewable->model),
            $reviewable instanceof TourismExperience => 'Expérience · '.$reviewable->title,
            default => 'Prestation',
        };
    }
}
