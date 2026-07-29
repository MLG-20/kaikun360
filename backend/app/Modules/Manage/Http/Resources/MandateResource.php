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
            // F7.3.a — Clauses du mandat : ce sont les « contrats » de la ligne
            // CDC §6 « Gestion locative ». Stockées depuis B4.6 mais jamais
            // exposées, donc invisibles partout.
            'terms' => $this->terms,

            // Agrégats (présents si chargés via withSum/withCount ; sinon 0).
            'summary' => [
                'loyers_payes_xof' => (int) ($this->loyers_payes ?? 0),
                'loyers_impayes_xof' => (int) ($this->loyers_impayes ?? 0),
                // Nombre d'échéances derrière chaque montant (désambiguïse deux
                // mois au même loyer, cf. withCount du contrôleur).
                'loyers_payes_count' => (int) ($this->loyers_payes_count ?? 0),
                'loyers_impayes_count' => (int) ($this->loyers_impayes_count ?? 0),
                'depenses_xof' => (int) ($this->depenses_total ?? 0),
                'reversements_xof' => (int) ($this->reversements_effectues ?? 0),
                'incidents_ouverts' => (int) ($this->incidents_ouverts ?? 0),
            ],

            // Compteurs bruts de supervision back-office : le contrôleur admin
            // (AdminDossierController::mandates) fait withCount(['rents',
            // 'incidents', 'expenses', 'payouts']) mais n'alimente pas les alias
            // détaillés du `summary` ci-dessus (réservés à la fiche F4.4). On
            // expose donc ces comptes bruts via whenCounted : présents seulement
            // quand ils ont été comptés, sinon la clé n'apparaît pas.
            'rents_count' => $this->whenCounted('rents'),
            'incidents_count' => $this->whenCounted('incidents'),
            'expenses_count' => $this->whenCounted('expenses'),
            'payouts_count' => $this->whenCounted('payouts'),

            'property' => PropertyResource::make($this->whenLoaded('property')),

            // Lignes détaillées (échéances de loyer, reversements, incidents) —
            // exposées UNIQUEMENT sur la fiche d'un mandat (F4.4), pas dans la
            // liste. On teste `relationLoaded` (et non `whenLoaded`) car
            // `RentResource::collection(MissingValue)` casserait ; absente au
            // catalogue, la clé n'apparaît simplement pas → aucun N+1.
            'rents' => $this->when(
                $this->relationLoaded('rents'),
                fn () => RentResource::collection($this->rents),
            ),
            'payouts' => $this->when(
                $this->relationLoaded('payouts'),
                fn () => OwnerPayoutResource::collection($this->payouts),
            ),
            'incidents' => $this->when(
                $this->relationLoaded('incidents'),
                fn () => IncidentResource::collection($this->incidents),
            ),
            'expenses' => $this->when(
                $this->relationLoaded('expenses'),
                fn () => ExpenseResource::collection($this->expenses),
            ),
        ];
    }
}
