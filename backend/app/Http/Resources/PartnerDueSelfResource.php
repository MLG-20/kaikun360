<?php

namespace App\Http\Resources;

use App\Models\PartnerDue;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Pro\Models\ProviderMission;
use App\Modules\Stay\Models\Stay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une dette envers un partenaire, vue du PARTENAIRE lui-même (« Mes
 * reversements », self-service).
 *
 * ⚠️ **N'expose PAS `commission_xof`** : c'est ce que Kaikun retient, pas
 * l'affaire du partenaire — contrairement à `PartnerDueResource` (back-office),
 * qui elle le montre à l'agent. `beneficiary` est omis pour la même raison
 * qu'un partenaire n'a pas besoin qu'on lui redise qui il est.
 *
 * @mixin PartnerDue
 */
class PartnerDueSelfResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,

            'source' => [
                'type' => class_basename((string) $this->source_type),
                'label' => $this->sourceLabel(),
            ],

            'gross_xof' => $this->gross_xof,
            'net_xof' => $this->net_xof,

            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'eligible_at' => $this->eligible_at,

            'created_at' => $this->created_at,
        ];
    }

    /** Même logique de libellé que `PartnerDueResource` (back-office). */
    private function sourceLabel(): ?string
    {
        $source = $this->source;

        if ($source === null) {
            return null;
        }

        if ($source instanceof ProviderMission) {
            return $source->title;
        }

        $cible = $source->bookable ?? null;

        return match (true) {
            $cible instanceof Stay => $cible->property?->title,
            $cible instanceof TourismExperience => $cible->title,
            $cible instanceof Vehicle => trim(($cible->brand ?? '').' '.($cible->model ?? ''))
                ?: $cible->type?->label(),
            $cible instanceof MobilityService => "{$cible->departure} → {$cible->destination}",
            default => null,
        };
    }
}
