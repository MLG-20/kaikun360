<?php

namespace App\Modules\Manage\Http\Resources;

use App\Modules\Immo\Http\Resources\PropertyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un mandat de gestion locative, avec ses agrégats
 * financiers (loyers, dépenses, reversements, incidents) lorsqu'ils ont été
 * calculés par le contrôleur (withSum/withCount).
 *
 * @mixin \App\Modules\Manage\Models\ManagementMandate
 */
class MandateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'commission_rate' => $this->commission_rate,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),

            // Agrégats (présents si chargés via withSum/withCount ; sinon 0).
            'summary' => [
                'loyers_payes_xof' => (int) ($this->loyers_payes ?? 0),
                'loyers_impayes_xof' => (int) ($this->loyers_impayes ?? 0),
                'depenses_xof' => (int) ($this->depenses_total ?? 0),
                'reversements_xof' => (int) ($this->reversements_effectues ?? 0),
                'incidents_ouverts' => (int) ($this->incidents_ouverts ?? 0),
            ],

            'property' => PropertyResource::make($this->whenLoaded('property')),
        ];
    }
}
