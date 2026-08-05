<?php

namespace App\Http\Resources;

use App\Enums\BookingStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'une réservation (transversale).
 *
 * Enrichie en F3.4 (espace client) pour l'écran « Mes réservations » : au-delà
 * des attributs bruts, la ressource expose le **type** de la chose réservée
 * (`bookable` polymorphe : nuitée, véhicule, expérience, trajet), un **libellé
 * lisible** de cet élément et un drapeau **`cancellable`** indiquant si le
 * client peut encore annuler lui-même (seuls les véhicules et les expériences
 * ont un endpoint d'annulation client, et uniquement tant que la réservation
 * n'est pas déjà annulée). Le libellé de l'élément n'est calculé que si la
 * relation `bookable` a été chargée en amont (évite les requêtes N+1).
 *
 * @mixin \App\Models\Booking
 */
class BookingResource extends JsonResource
{
    /** Types de `bookable` dont le client peut déclencher l'annulation lui-même. */
    private const CANCELLABLE_TYPES = ['vehicle', 'experience'];

    /**
     * Types de `bookable` qui se notent (F8.15.a) — miroir des clés réservables
     * de `Review::TYPES`. Le trajet (`mobility`) en est absent : le service rendu
     * est celui du véhicule et de son chauffeur, que `Review::TYPES` ne sait pas
     * désigner depuis un départ. Le sur-mesure aussi (il n'a pas de fiche).
     */
    private const REVIEWABLE_TYPES = ['stay', 'vehicle', 'experience'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->bookableType();

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            // Nature de la réservation (nuitée/véhicule/expérience/trajet).
            'type' => $type,
            'type_label' => $this->typeLabel($type),
            // Libellé de l'élément réservé (si la relation bookable est chargée).
            'item_label' => $this->whenLoaded('bookable', fn () => $this->itemLabel($type)),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'guests' => $this->guests,
            'amount_xof' => $this->amount_xof,
            'commission_xof' => $this->commission_xof,
            // --- État de règlement (F8.6) -----------------------------------
            // Le client pouvait réserver sans jamais pouvoir payer : l'API ne
            // disait pas ce qui restait dû, donc aucun écran ne pouvait le
            // demander. `montantPaye()` ne compte que les paiements ENCAISSÉS.
            // ⚠️ Requiert `payments` en eager loading côté contrôleur, sinon une
            // liste de 15 réservations déclenche 15 requêtes de plus.
            'paid_xof' => $this->montantPaye(),
            'remaining_xof' => $this->resteAPayer(),
            'is_paid' => $this->estPayee(),
            // Peut-on lancer un règlement ? Une réservation annulée ne se paie
            // pas, une réservation soldée non plus.
            'payable' => ! ($this->status?->estAnnulee() ?? false) && $this->resteAPayer() > 0,
            'caution_xof' => $this->caution_xof,
            'caution_status' => $this->caution_status?->value,
            'cancelled_at' => $this->cancelled_at,
            'created_at' => $this->created_at,
            // Le titulaire peut-il encore annuler cette réservation lui-même ?
            'cancellable' => in_array($type, self::CANCELLABLE_TYPES, true)
                && ! ($this->status?->estAnnulee() ?? false),
            // --- Avis (F8.15.a) ---------------------------------------------
            // La cible d'un avis n'est PAS la réservation mais la chose réservée
            // (`Review::TYPES`) : on note un logement, pas son contrat. Le front
            // a donc besoin du couple, qu'il ne peut pas deviner depuis `id`.
            // `null` pour le sur-mesure, qui ne se note pas (le devis n'est pas
            // une fiche du catalogue).
            'reviewable_type' => in_array($type, self::REVIEWABLE_TYPES, true) ? $type : null,
            'reviewable_id' => in_array($type, self::REVIEWABLE_TYPES, true) ? $this->bookable_id : null,
            // Miroir exact de `ReviewPolicy::create` + `Review::hasConsumed` : on
            // ne note qu'un service **terminé**. ⚠️ Ne dit PAS si un avis existe
            // déjà — ça, c'est `GET /reviews/mine` (une requête par ligne sinon).
            'can_review' => in_array($type, self::REVIEWABLE_TYPES, true)
                && $this->status === BookingStatus::TERMINEE,
        ];
    }

    /**
     * Slug du type de `bookable` déduit du nom court de la classe polymorphe
     * (sans coupler cette ressource transversale aux modèles des modules).
     */
    private function bookableType(): string
    {
        return match (class_basename((string) $this->bookable_type)) {
            'Stay' => 'stay',
            'Vehicle' => 'vehicle',
            'TourismExperience' => 'experience',
            'MobilityService' => 'mobility',
            // F8.11 — le sur-mesure (chantier, mandat, diaspora, team building)
            // n'a aucune fiche au catalogue à désigner : la cible réservée est
            // le DEVIS lui-même, avec son montant et ses lignes.
            'Quote' => 'quote',
            // F8.14 — le team building a ses PROPRES devis : c'est le devis
            // accepté (lignes + total + marge) qui est réservé, pas une fiche.
            'TeamBuildingQuote' => 'team_building',
            'ConstructionQuote' => 'construction',
            default => 'autre',
        };
    }

    /** Libellé français du type de réservation. */
    private function typeLabel(string $type): string
    {
        return match ($type) {
            'stay' => 'Nuitée',
            'vehicle' => 'Véhicule',
            'experience' => 'Expérience',
            'mobility' => 'Trajet',
            'quote' => 'Prestation sur-mesure',
            'team_building' => 'Team building',
            'construction' => 'Chantier',
            default => 'Réservation',
        };
    }

    /**
     * Libellé lisible de l'élément réservé, calculé selon son type (chaque
     * bookable a ses propres attributs : une nuitée n'a de nom que via son bien,
     * un véhicule via marque/modèle, un trajet via son itinéraire…).
     */
    private function itemLabel(string $type): ?string
    {
        $bookable = $this->bookable;

        if ($bookable === null) {
            return null;
        }

        return match ($type) {
            'stay' => $bookable->property?->title
                ? 'Nuitée — '.$bookable->property->title
                : 'Nuitée',
            'vehicle' => trim(($bookable->brand ?? '').' '.($bookable->model ?? '')) ?: 'Véhicule',
            'experience' => $bookable->title ?? 'Expérience',
            'mobility' => trim(($bookable->departure ?? '').' → '.($bookable->destination ?? ''), ' →') ?: 'Trajet',
            // Le libellé vient de l'UNIVERS de la demande d'origine (« Chantier
            // de construction », « Gestion locative »…) : « devis QTE-XXXX » ne
            // dirait rien au client dans sa liste de réservations.
            'quote' => $bookable->request?->service_type?->label()
                ? 'Prestation — '.$bookable->request->service_type->label()
                : 'Prestation sur-mesure',
            // La ville et l'effectif disent l'événement mieux que sa référence :
            // « Séminaire — Saly, 24 participants ».
            // L'objectif et la ville disent le chantier : « Chantier — Villa
            // R+1, Mbour » plutôt qu'une référence de devis.
            'construction' => 'Chantier'
                .($bookable->constructionRequest?->objective
                    ? ' — '.$bookable->constructionRequest->objective
                    : '')
                .($bookable->constructionRequest?->city
                    ? ', '.$bookable->constructionRequest->city
                    : ''),
            'team_building' => 'Séminaire'
                .($bookable->request?->city ? ' — '.$bookable->request->city : '')
                .($bookable->request?->participants
                    ? ', '.$bookable->request->participants.' participants'
                    : ''),
            default => null,
        };
    }
}
