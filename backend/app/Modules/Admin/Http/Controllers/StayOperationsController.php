<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\CautionStatus;
use App\Enums\HousekeepingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Modules\Admin\Validation\MediaEntry;
use App\Modules\Admin\Validation\OwnerEntry;
use App\Modules\Stay\Models\Stay;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/**
 * Exploitation des nuitées côté back-office (B13.6) : calendrier global,
 * check-in / check-out et suivi du ménage. Réservé à la permission
 * `gerer:nuitees` (agents + admin).
 *
 * Ces opérations ne concernent que les réservations de type Stay ; toute
 * réservation d'un autre type est rejetée (422).
 */
class StayOperationsController extends Controller
{
    /**
     * Calendrier global des séjours. GET /api/v1/admin/stays/calendar
     *
     * Filtres : `from`, `to` (bornes sur la date d'arrivée).
     */
    public function calendar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));

        $bookings = Booking::query()
            ->where('bookable_type', Stay::class)
            ->when($data['from'] ?? null, fn ($q, $from) => $q->whereDate('start_date', '>=', $from))
            ->when($data['to'] ?? null, fn ($q, $to) => $q->whereDate('start_date', '<=', $to))
            ->orderBy('start_date')
            ->paginate($perPage);

        // Pré-chargement des nuitées + titres de biens (évite les N+1).
        $stays = Stay::with('property:id,title')
            ->whereIn('id', collect($bookings->items())->pluck('bookable_id'))
            ->get()
            ->keyBy('id');

        $bookings->through(fn (Booking $b) => [
            'booking_id' => $b->id,
            'reference' => $b->reference,
            'stay_id' => $b->bookable_id,
            'property_title' => $stays->get($b->bookable_id)?->property?->title,
            'start_date' => $b->start_date?->toDateString(),
            'end_date' => $b->end_date?->toDateString(),
            'guests' => $b->guests,
            'status' => $b->status->value,
            'checked_in_at' => $b->checked_in_at,
            'checked_out_at' => $b->checked_out_at,
            'housekeeping_status' => $b->housekeeping_status?->value,
            // F7.3.f — sans ces deux clés, l'écran ne peut ni afficher la caution
            // ni savoir si elle reste à trancher.
            'caution_xof' => $b->caution_xof,
            'caution_status' => $b->caution_status?->value,
        ]);

        return ApiResponse::paginated($bookings);
    }

    /**
     * Dossier complet d'un séjour. GET /api/v1/admin/stay-bookings/{booking}
     *
     * **F8.2.a — pourquoi une fiche.** Le calendrier est une vue d'exploitation :
     * il dit *qui arrive quand* et rien de plus. Dès qu'un client appelle (« ma
     * caution ? », « j'ai déjà payé l'acompte »), l'agent devait sauter d'écran en
     * écran — Paiements pour l'argent, Comptes pour le client, Catalogues pour le
     * bien — sans jamais voir le séjour d'un seul tenant. Cette fiche rassemble
     * les quatre faces d'un séjour :
     *   1. **le séjour** (dates, nuits, voyageurs, phase d'exploitation) ;
     *   2. **le logement** (bien, localisation, capacité, tarif, photos) et son
     *      **hôte**, joignable ;
     *   3. **le client** et **l'argent** (montant, encaissé, reste à payer, les
     *      paiements un par un) ;
     *   4. **la trace** : le journal d'audit du séjour — c'est là qu'apparaît le
     *      motif d'une caution conservée, qui fait foi en cas de contestation.
     *
     * Lecture seule : les gestes (arrivée, départ, ménage, caution) restent aux
     * routes PATCH déjà en place, que la fiche appelle comme le fait la liste.
     */
    public function show(Booking $booking): JsonResponse
    {
        $this->assertStay($booking);

        // Le bien porte le titre, la localisation et les photos ; la nuitée porte
        // les règles d'exploitation (capacité, horaires, tarif). Chargement en un
        // seul passage pour ne pas multiplier les requêtes.
        $stay = Stay::with(['property.owner', 'property.region', 'property.department', 'property.commune', 'property.allMedia'])
            ->find($booking->bookable_id);

        $property = $stay?->property;

        $payments = $booking->payments()->latest()->get()->map(fn (Payment $payment) => [
            'id' => $payment->id,
            'reference' => $payment->reference,
            'amount_xof' => $payment->amount_xof,
            'kind' => $payment->kind?->value,
            'kind_label' => $payment->kind?->label(),
            'status' => $payment->status->value,
            'status_label' => $payment->status->label(),
            'mode' => $payment->mode,
            'provider' => $payment->provider,
            'created_at' => $payment->created_at,
        ]);

        // Journal d'audit du séjour : décisions de caution (avec leur motif) et
        // toute autre action tracée sur la réservation. 30 dernières entrées.
        $activity = Activity::query()
            ->where('subject_type', $booking->getMorphClass())
            ->where('subject_id', $booking->id)
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
            ]);

        return ApiResponse::success([
            'booking' => [
                'booking_id' => $booking->id,
                'reference' => $booking->reference,
                'status' => $booking->status->value,
                'start_date' => $booking->start_date?->toDateString(),
                'end_date' => $booking->end_date?->toDateString(),
                // Le nombre de nuits n'est stocké nulle part : il se déduit des
                // bornes, et c'est l'unité de facturation du module.
                'nights' => $booking->start_date && $booking->end_date
                    ? max(0, $booking->start_date->diffInDays($booking->end_date))
                    : null,
                'guests' => $booking->guests,
                'amount_xof' => $booking->amount_xof,
                'commission_xof' => $booking->commission_xof,
                'paid_xof' => $booking->montantPaye(),
                'remaining_xof' => $booking->resteAPayer(),
                'created_at' => $booking->created_at,
                'cancelled_at' => $booking->cancelled_at,
                'checked_in_at' => $booking->checked_in_at,
                'checked_out_at' => $booking->checked_out_at,
                'housekeeping_status' => $booking->housekeeping_status?->value,
                'caution_xof' => $booking->caution_xof,
                'caution_status' => $booking->caution_status?->value,
            ],
            'client' => OwnerEntry::from($booking->user),
            // Un bien peut avoir été retiré depuis la réservation : la fiche doit
            // rester consultable (le séjour, lui, a bien eu lieu).
            'stay' => $stay === null ? null : [
                'stay_id' => $stay->id,
                'property_id' => $property?->id,
                'property_title' => $property?->title,
                'property_type' => $property?->type?->value,
                'address' => $property?->address,
                'commune' => $property?->commune?->name,
                'department' => $property?->department?->name,
                'region' => $property?->region?->name,
                'capacity' => $stay->capacity,
                'price_per_night_xof' => $stay->price_per_night_xof,
                'check_in_time' => $stay->check_in_time,
                'check_out_time' => $stay->check_out_time,
                'is_active' => $stay->is_active,
                'host' => OwnerEntry::from($property?->owner),
                'media' => $property === null ? null : MediaEntry::summary($property),
            ],
            'payments' => $payments,
            'activity' => $activity,
        ]);
    }

    /**
     * Enregistre l'arrivée. PATCH /api/v1/admin/stay-bookings/{booking}/check-in
     */
    public function checkIn(Booking $booking): JsonResponse
    {
        $this->assertStay($booking);

        if ($booking->checked_in_at !== null) {
            throw ValidationException::withMessages(['check_in' => ['Arrivée déjà enregistrée.']]);
        }

        $booking->update(['checked_in_at' => now()]);

        return ApiResponse::success(['booking' => $this->summary($booking->fresh())]);
    }

    /**
     * Enregistre le départ et déclenche le ménage.
     * PATCH /api/v1/admin/stay-bookings/{booking}/check-out
     *
     * **F8.15.a — le départ clôt aussi la réservation.** Jusqu'ici cette méthode
     * n'horodatait que ses propres colonnes : le séjour était constaté fini côté
     * exploitation, mais la réservation restait `confirmee` pour l'éternité. Le
     * départ enregistré par un agent est pourtant la preuve la plus sûre qu'un
     * séjour a eu lieu — plus sûre que la date de fin, qu'un départ anticipé
     * dément. C'est donc lui qui pose `terminee`, sans attendre la tâche
     * planifiée `reservations:cloturer` (qui ne rattrape que les séjours dont
     * personne n'a enregistré le départ).
     */
    public function checkOut(Booking $booking): JsonResponse
    {
        $this->assertStay($booking);

        if ($booking->checked_in_at === null) {
            throw ValidationException::withMessages(['check_out' => ["Le départ exige une arrivée préalable."]]);
        }
        if ($booking->checked_out_at !== null) {
            throw ValidationException::withMessages(['check_out' => ['Départ déjà enregistré.']]);
        }

        $booking->update([
            'checked_out_at' => now(),
            'housekeeping_status' => HousekeepingStatus::A_FAIRE->value,
            // Une réservation annulée qui aurait malgré tout été occupée reste
            // annulée : on ne réécrit pas une décision d'annulation.
            ...($booking->status->estAnnulee() ? [] : ['status' => BookingStatus::TERMINEE->value]),
        ]);

        return ApiResponse::success(['booking' => $this->summary($booking->fresh())]);
    }

    /**
     * Met à jour le statut de ménage.
     * PATCH /api/v1/admin/stay-bookings/{booking}/housekeeping
     */
    public function housekeeping(Request $request, Booking $booking): JsonResponse
    {
        $this->assertStay($booking);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', HousekeepingStatus::values())],
        ]);

        $booking->update(['housekeeping_status' => $data['status']]);

        return ApiResponse::success(['booking' => $this->summary($booking->fresh())]);
    }

    /**
     * Tranche le sort de la caution après le départ.
     * PATCH /api/v1/admin/stay-bookings/{booking}/caution
     *
     * Comble le dernier manque du module *Nuitées* du CDC §6 (F7.3.f) : la caution
     * était recopiée sur la réservation mais **jamais suivie** — ni retenue, ni
     * restitution. Elle est désormais `retenue` dès la réservation (module Stay) ;
     * il reste à la **restituer** ou à la **conserver** en fin de séjour.
     *
     * Trois garde-fous :
     *  - **départ enregistré exigé.** Restituer avant le départ n'a pas de sens, et
     *    conserver une caution alors que le client est encore sur place, c'est
     *    trancher avant d'avoir vu l'état des lieux. Le ménage, lui, n'est pas
     *    exigé : une caution peut se rendre sans attendre la fin du nettoyage.
     *  - **caution encore retenue exigée** : on ne rejoue pas une décision prise.
     *  - **motif obligatoire pour la conserver** — une caution perdue se justifie
     *    (litige possible). La restitution, elle, n'a rien à justifier.
     *
     * La décision est tracée au journal d'audit avec son motif et son montant.
     */
    public function caution(Request $request, Booking $booking): JsonResponse
    {
        $this->assertStay($booking);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.CautionStatus::RESTITUEE->value.','.CautionStatus::PERDUE->value],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($booking->checked_out_at === null) {
            throw ValidationException::withMessages([
                'caution' => ['Le sort de la caution se décide après le départ du client.'],
            ]);
        }

        if ($booking->caution_status !== CautionStatus::RETENUE) {
            throw ValidationException::withMessages([
                'caution' => [$booking->caution_status === null
                    ? "Cette réservation n'a pas de caution."
                    : 'La caution a déjà été tranchée.'],
            ]);
        }

        $status = CautionStatus::from($data['status']);
        $reason = trim((string) ($data['reason'] ?? ''));

        if ($status === CautionStatus::PERDUE && $reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['Indiquez le motif de la retenue de la caution.'],
            ]);
        }

        $booking->update(['caution_status' => $status->value]);

        activity()
            ->causedBy($request->user())
            ->performedOn($booking)
            ->withProperties([
                'caution_status' => $status->value,
                'caution_xof' => (int) $booking->caution_xof,
                'reason' => $reason !== '' ? $reason : null,
            ])
            ->log($status === CautionStatus::RESTITUEE ? 'Caution restituée' : 'Caution conservée');

        return ApiResponse::success(['booking' => $this->summary($booking->fresh())]);
    }

    /**
     * Rejette (422) toute réservation qui n'est pas une nuitée.
     */
    private function assertStay(Booking $booking): void
    {
        if ($booking->bookable_type !== Stay::class) {
            throw ValidationException::withMessages([
                'booking' => ['Cette opération ne concerne que les nuitées.'],
            ]);
        }
    }

    /**
     * Résumé d'exploitation d'une réservation de nuitée.
     *
     * @return array<string, mixed>
     */
    private function summary(Booking $booking): array
    {
        return [
            'booking_id' => $booking->id,
            'reference' => $booking->reference,
            'status' => $booking->status->value,
            'checked_in_at' => $booking->checked_in_at,
            'checked_out_at' => $booking->checked_out_at,
            'housekeeping_status' => $booking->housekeeping_status?->value,
            'caution_xof' => $booking->caution_xof,
            'caution_status' => $booking->caution_status?->value,
        ];
    }
}
