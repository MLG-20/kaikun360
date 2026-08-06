<?php

namespace App\Modules\Admin\Http\Resources;

use App\Models\PartnerDue;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Pro\Models\ProviderMission;
use App\Modules\Stay\Models\Stay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une dette envers un partenaire, vue du back-office (F8.16.a).
 *
 * ⚠️ **Ressource strictement back-office** : elle nomme le bénéficiaire et
 * expose la commission prélevée par Kaikun. Elle n'a rien à faire dans une
 * réponse publique.
 *
 * @mixin PartnerDue
 */
class PartnerDueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,

            'beneficiary' => [
                'id' => $this->beneficiary_id,
                'name' => $this->beneficiary?->name,
                'email' => $this->beneficiary?->email,
                'phone' => $this->beneficiary?->phone,
            ],

            // D'où vient la dette. Le libellé est calculé côté serveur : une
            // réservation, une mission et un séminaire ne se nomment pas pareil,
            // et l'écran n'a pas à connaître ces cinq cas.
            'source' => [
                'type' => class_basename((string) $this->source_type),
                'id' => $this->source_id,
                'reference' => $this->source?->reference,
                'label' => $this->sourceLabel(),
            ],

            'gross_xof' => $this->gross_xof,
            'commission_xof' => $this->commission_xof,
            'net_xof' => $this->net_xof,

            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'eligible_at' => $this->eligible_at,
            // Le serveur tranche : l'écran n'a pas à rejouer « exigible ET sans
            // lot ». Miroir exact du scope `payables()`.
            'is_payable' => $this->status->estPayable() && $this->payout_id === null,

            'payout_id' => $this->payout_id,
            'cancelled_reason' => $this->cancelled_reason,
            'cancelled_at' => $this->cancelled_at,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Nom lisible de ce qui a été vendu, quel que soit l'univers.
     *
     * ⚠️ Sans ce libellé, l'écran afficherait « Booking #42 » à un agent qui
     * doit décider d'un virement : il faut reconnaître le service au premier
     * coup d'œil.
     *
     * ⚠️ **Les cinq cas sont nommés un par un, délibérément.** Une première
     * version cherchait `isset($cible->title)` : c'est vrai pour un circuit et
     * une mission, faux pour un **véhicule** (qui porte `brand`/`model`) et pour
     * un **trajet** (qui porte `departure`/`destination`). Le repli affichait
     * alors le nom de la classe — « Vehicle », « MobilityService » — sur l'écran
     * même où l'on décide d'un virement. Un `match` sur le type dégrade
     * bruyamment : ajouter un univers sans y penser tombe sur le `default` et se
     * voit tout de suite.
     */
    private function sourceLabel(): ?string
    {
        $source = $this->source;

        if ($source === null) {
            return null;
        }

        // Une mission prestataire porte son propre titre (team building,
        // construction) : c'est l'intitulé du travail confié.
        if ($source instanceof ProviderMission) {
            return $source->title;
        }

        $cible = $source->bookable ?? null;

        return match (true) {
            // Une nuitée n'a pas de titre propre : c'est le BIEN qui le porte.
            $cible instanceof Stay => $cible->property?->title,
            $cible instanceof TourismExperience => $cible->title,
            // Un véhicule se reconnaît à sa marque et son modèle, à défaut à son
            // type. ⚠️ `type` est casté en enum `VehicleType`, pas en chaîne :
            // le rendre tel quel lève une TypeError (trouvé en interrogeant le
            // serveur réel, pas en test — les factories renseignent la marque,
            // si bien que ce repli n'y était jamais atteint).
            $cible instanceof Vehicle => trim(($cible->brand ?? '').' '.($cible->model ?? ''))
                ?: $cible->type?->label(),
            // Un trajet est un couple de villes : c'est ainsi qu'il est vendu.
            $cible instanceof MobilityService => "{$cible->departure} → {$cible->destination}",
            default => null,
        };
    }
}
