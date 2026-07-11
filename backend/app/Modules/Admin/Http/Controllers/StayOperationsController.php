<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Enums\HousekeepingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Modules\Stay\Models\Stay;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        ]);

        return ApiResponse::paginated($bookings);
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
        ];
    }
}
