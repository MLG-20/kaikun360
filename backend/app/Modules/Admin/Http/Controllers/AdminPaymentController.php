<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Support\ApiResponse;
use App\Support\Payments\PaymentConfirmationService;
use App\Support\Payments\PaymentProviderInterface;
use App\Support\Payouts\PartnerDueRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/**
 * Supervision et remboursement des paiements par le back-office (B14.4).
 *
 * Réservé à la permission `gerer:paiements`. Le remboursement (caution à
 * restituer, annulation éligible côté Mobility / Explore / Stay) est délégué au
 * PSP via l'abstraction, jamais à PayTech directement.
 */
class AdminPaymentController extends Controller
{
    public function __construct(
        private readonly PaymentProviderInterface $provider,
        private readonly PaymentConfirmationService $confirmation,
        private readonly PartnerDueRegistrar $dues,
    ) {
    }

    /**
     * Liste des paiements. GET /api/v1/admin/payments
     *
     * Filtres : `status`, `booking_id`, `reference` (référence interne ou PSP).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));

        $payments = Payment::query()
            // F7.3.h : `booking.payments` pré-chargé pour calculer le reste à
            // payer sans N+1 (cf. Booking::montantPaye).
            ->with('booking.payments')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('booking_id'), fn ($q) => $q->where('booking_id', $request->integer('booking_id')))
            ->when($request->filled('reference'), function ($q) use ($request) {
                $term = '%'.$request->string('reference')->toString().'%';
                $q->where(fn ($w) => $w->where('reference', 'like', $term)->orWhere('provider_reference', 'like', $term));
            })
            ->latest()
            ->paginate($perPage);

        return PaymentResource::collection($payments);
    }

    /**
     * Dossier complet d'un paiement. GET /api/v1/admin/payments/{payment}
     *
     * **F8.2.d — l'écran le plus sensible du back-office.** Un règlement est ce
     * qui fait tourner la plateforme : le confirmer à tort crédite une
     * réservation jamais payée, le rembourser à tort sort de l'argent réel. Ces
     * deux gestes se prenaient depuis une ligne de tableau, sans jamais voir ce
     * qui les justifie.
     *
     * La fiche rassemble les quatre choses qu'un agent doit avoir sous les yeux :
     *   1. **la transaction** — montant, nature, mode, statut, et surtout les
     *      éléments de PREUVE : référence PSP, signature vérifiée, référence de
     *      la transaction Wave/OM saisie à la confirmation manuelle, montant déjà
     *      remboursé. Sans eux, « confirmer » est un acte de foi ;
     *   2. **la réservation** qu'il paie — la ressource, les dates, le client ;
     *   3. **l'échéancier complet** : TOUS les règlements de cette réservation.
     *      Un acompte isolé ne dit rien ; le même acompte à côté d'un solde déjà
     *      encaissé et d'un remboursement partiel raconte une autre histoire ;
     *   4. **le journal** — qui a confirmé, qui a remboursé, de combien, quand.
     *
     * ⚠️ `signature_verified` et `meta` ne sont PAS exposés par
     * `PaymentResource` (servie aussi à l'espace client) : ce sont des données de
     * contrôle. Elles sont construites ici, derrière la garde `gerer:paiements`.
     */
    public function show(Payment $payment): JsonResponse
    {
        $payment->load(['booking.payments', 'booking.user', 'booking.bookable']);

        $booking = $payment->booking;
        $meta = $payment->meta ?? [];

        // L'échéancier : tous les règlements de la MÊME réservation, celui-ci
        // compris. C'est le seul angle depuis lequel un acompte a du sens.
        $siblings = $booking === null ? collect() : $booking->payments
            ->sortByDesc('created_at')
            ->values()
            ->map(fn (Payment $p) => [
                'id' => $p->id,
                'reference' => $p->reference,
                'amount_xof' => $p->amount_xof,
                'kind_label' => $p->kind?->label(),
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'mode' => $p->mode,
                'created_at' => $p->created_at,
                // Marque la ligne courante : l'agent doit se situer dans la série.
                'is_current' => $p->id === $payment->id,
            ]);

        return ApiResponse::success([
            'payment' => [
                'id' => $payment->id,
                'reference' => $payment->reference,
                'booking_id' => $payment->booking_id,
                'amount_xof' => $payment->amount_xof,
                'commission_xof' => $payment->commission_xof,
                'kind' => $payment->kind?->value,
                'kind_label' => $payment->kind?->label(),
                'status' => $payment->status->value,
                'status_label' => $payment->status->label(),
                'mode' => $payment->mode,
                'provider' => $payment->provider,
                'created_at' => $payment->created_at,
                'updated_at' => $payment->updated_at,

                // --- Éléments de preuve (back-office uniquement).
                'provider_reference' => $payment->provider_reference,
                'signature_verified' => (bool) $payment->signature_verified,
                'manual_proof_reference' => $meta['manual_proof_reference'] ?? null,
                'refunded_amount_xof' => $meta['refunded_amount_xof'] ?? null,

                // --- Ce que l'agent a le droit de faire, décidé par le SERVEUR.
                // L'écran n'a pas à réinventer ces règles : il affiche ce que
                // l'API accepterait, et rien d'autre.
                'can_confirm' => $payment->mode === 'manuel'
                    && $payment->status !== PaymentStatus::COMPLETE,
                'can_refund' => $payment->status === PaymentStatus::COMPLETE,
            ],
            'booking' => $booking === null ? null : [
                'id' => $booking->id,
                'reference' => $booking->reference,
                'resource_type' => class_basename($booking->bookable_type),
                'resource_label' => $this->bookableLabel($booking),
                'start_date' => $booking->start_date?->toDateString(),
                'end_date' => $booking->end_date?->toDateString(),
                'guests' => $booking->guests,
                'status' => $booking->status->value,
                'amount_xof' => $booking->amount_xof,
                'paid_xof' => $booking->montantPaye(),
                'remaining_xof' => $booking->resteAPayer(),
                'client' => $booking->user === null ? null : [
                    'id' => $booking->user->id,
                    'name' => $booking->user->name,
                    'email' => $booking->user->email,
                    'phone' => $booking->user->phone,
                ],
            ],
            'siblings' => $siblings,
            'activity' => Activity::query()
                ->where('subject_type', $payment->getMorphClass())
                ->where('subject_id', $payment->id)
                ->with('causer')
                ->latest()
                ->limit(30)
                ->get()
                ->map(fn (Activity $entry) => [
                    'id' => $entry->id,
                    'description' => $entry->description,
                    'causer_name' => $entry->causer?->name,
                    'properties' => $entry->properties,
                    'created_at' => $entry->created_at,
                ]),
        ]);
    }

    /**
     * Intitulé lisible de la ressource réservée, quel qu'en soit le type.
     *
     * Les quatre réservables n'ont pas le même champ d'intitulé (un trajet n'a
     * pas de titre, il a un départ et une destination) : on prend le premier
     * présent plutôt que d'écrire un `match` sur les classes, qui casserait au
     * prochain type réservable.
     */
    private function bookableLabel(\App\Models\Booking $booking): string
    {
        $resource = $booking->bookable;

        if ($resource === null) {
            return 'Ressource retirée';
        }

        if (! empty($resource->departure) && ! empty($resource->destination)) {
            return $resource->departure.' → '.$resource->destination;
        }

        return $resource->title
            ?? $resource->business_name
            ?? $resource->name
            // Une nuitée n'a pas de titre : le sien est celui de son bien.
            ?? $resource->property?->title
            ?? trim(($resource->brand ?? '').' '.($resource->model ?? ''))
            ?: '#'.$booking->bookable_id;
    }

    /**
     * Rembourse tout ou partie d'un paiement encaissé.
     * POST /api/v1/admin/payments/{payment}/refund
     */
    public function refund(Request $request, Payment $payment): JsonResponse
    {
        $data = $request->validate([
            'amount_xof' => ['nullable', 'integer', 'min:1'],
        ]);

        // Seul un paiement réellement encaissé est remboursable.
        if ($payment->status !== PaymentStatus::COMPLETE) {
            throw ValidationException::withMessages([
                'payment' => ['Seul un paiement encaissé peut être remboursé.'],
            ]);
        }

        $amount = $data['amount_xof'] ?? $payment->amount_xof;
        if ($amount > $payment->amount_xof) {
            throw ValidationException::withMessages([
                'amount_xof' => ['Le montant remboursé ne peut dépasser le montant payé.'],
            ]);
        }

        // ⚠️ F8.5 — PayTech ne rembourse que la TOTALITÉ : sa route
        // `refund-payment` ne prend qu'une référence de commande, pas de montant.
        // L'écran proposait donc un remboursement partiel que le PSP n'aurait
        // jamais exécuté — l'agent aurait vu « remboursé » pour une opération
        // qui n'a pas eu lieu. On refuse explicitement plutôt que de mentir ;
        // un remboursement partiel se règle hors PSP (virement Wave/OM) puis se
        // trace ici comme un paiement manuel.
        if ($payment->provider !== 'manuel' && $amount !== $payment->amount_xof) {
            throw ValidationException::withMessages([
                'amount_xof' => ['PayTech ne rembourse que la totalité du paiement. Pour un remboursement partiel, procédez hors plateforme et enregistrez-le manuellement.'],
            ]);
        }

        if (! $this->provider->refund($payment, $amount)) {
            return ApiResponse::error('Le remboursement a échoué côté PSP.', 502);
        }

        $payment->update([
            'status' => PaymentStatus::REMBOURSE->value,
            'meta' => array_merge($payment->meta ?? [], ['refunded_amount_xof' => $amount]),
        ]);

        // ⚠️ F8.16.a — un remboursement ÉTEINT la dette envers le partenaire.
        // Sans cela, le client est remboursé ET le partenaire payé : Kaikun perd
        // deux fois. La dette n'est éteinte que si elle est encore vivante ; si
        // le virement est déjà parti, la ligne reste « payée » et l'écart devient
        // une créance à régler hors application — la marquer annulée ferait
        // disparaître des comptes un virement bien réel.
        $eteinte = false;
        if ($payment->booking !== null) {
            $eteinte = $this->dues->cancelForSource(
                $payment->booking,
                "Réservation remboursée (paiement {$payment->reference})",
            );
        }

        activity()->causedBy($request->user())->performedOn($payment)
            ->withProperties(['amount_xof' => $amount, 'dette_partenaire_annulee' => $eteinte])
            ->log('Remboursement de paiement');

        return ApiResponse::success(['payment' => PaymentResource::make($payment->fresh())]);
    }

    /**
     * Confirme manuellement un paiement encaissé hors PSP (Phase 1 du cahier des
     * charges). POST /api/v1/admin/payments/{payment}/confirm
     *
     * Cas d'usage : le client a réglé par Wave/Orange Money au numéro officiel ;
     * un admin valide la réception. La réservation est alors confirmée via le
     * service partagé, avec le causer admin (traçabilité, B15.3).
     */
    public function confirm(Request $request, Payment $payment): JsonResponse
    {
        $data = $request->validate([
            // Identifiant de la transaction Wave/OM, conservé comme preuve.
            'provider_reference' => ['nullable', 'string', 'max:255'],
        ]);

        // Réservé au flux manuel : un paiement PayTech se confirme par webhook.
        if ($payment->mode !== 'manuel') {
            throw ValidationException::withMessages([
                'payment' => ['Seul un paiement en mode manuel peut être confirmé à la main.'],
            ]);
        }
        if ($payment->status === PaymentStatus::COMPLETE) {
            throw ValidationException::withMessages([
                'payment' => ['Ce paiement est déjà confirmé.'],
            ]);
        }

        if (! empty($data['provider_reference'])) {
            $payment->provider_reference = $data['provider_reference'];
            $payment->meta = array_merge($payment->meta ?? [], [
                'manual_proof_reference' => $data['provider_reference'],
            ]);
        }

        $this->confirmation->markCompleted($payment, $request->user());

        return ApiResponse::success(['payment' => PaymentResource::make($payment->fresh())]);
    }
}
