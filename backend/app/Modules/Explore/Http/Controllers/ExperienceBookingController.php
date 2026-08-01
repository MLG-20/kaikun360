<?php

namespace App\Modules\Explore\Http\Controllers;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Modules\Explore\Http\Requests\StoreExperienceBookingRequest;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Explore\Services\ExperienceBookingService;
use App\Modules\Explore\Services\ExperienceCancellationService;
use App\Support\ApiResponse;
use App\Support\Billing\CommissionCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Disponibilité et réservation d'une expérience (phase B6.3).
 *
 * La capacité borne le nombre total de participants (panier groupe) ; on refuse
 * toute réservation dépassant les places restantes.
 */
class ExperienceBookingController extends Controller
{
    public function __construct(
        private readonly ExperienceBookingService $capacity,
        private readonly CommissionCalculator $commissions,
    ) {
    }

    /**
     * Places restantes. GET /api/v1/experiences/{id}/availability
     */
    public function availability(string $id): JsonResponse
    {
        $experience = TourismExperience::query()->published()->findOrFail($id);

        return ApiResponse::success([
            'experience_id' => $experience->id,
            'capacity' => $experience->capacity,
            'seats_left' => $this->capacity->seatsLeft($experience),
        ]);
    }

    /**
     * Réservation de places (groupe). POST /api/v1/experiences/{id}/bookings
     */
    public function store(StoreExperienceBookingRequest $request, string $id): JsonResponse
    {
        $experience = TourismExperience::query()->published()->findOrFail($id);
        $data = $request->validated();
        $guests = (int) $data['guests'];

        // Contrôle de capacité (places restantes).
        if (! $this->capacity->canAccommodate($experience, $guests)) {
            $left = $this->capacity->seatsLeft($experience);
            throw ValidationException::withMessages([
                'guests' => ["Il ne reste que {$left} place(s) disponible(s)."],
            ]);
        }

        // Date de départ choisie ; fin déduite de la durée du circuit.
        $start = Carbon::parse($data['start_date']);
        $end = $start->copy()->addDays(max(0, $experience->duration_days - 1));

        $montant = $guests * $experience->price_xof;

        $booking = $experience->bookings()->create([
            'reference' => 'BK-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
            'user_id' => $request->user()->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'guests' => $guests,
            'amount_xof' => $montant,
            // F8.4 — comme les nuitées, le tourisme n'enregistrait aucune
            // commission : la plateforme vendait des circuits sans trace de son
            // revenu. Taux paramétrable au back-office, figé à la réservation.
            'commission_xof' => $this->commissions->commissionFor($montant),
            'status' => BookingStatus::EN_ATTENTE->value, // en attente de paiement (B14)
        ]);

        activity()->causedBy($request->user())->performedOn($booking)->log('Réservation d\'expérience');

        return ApiResponse::created(['booking' => BookingResource::make($booking)]);
    }

    /**
     * Annulation d'une réservation par le client. PATCH /api/v1/experiences/bookings/{booking}/cancel
     *
     * Seul le titulaire peut annuler sa réservation d'expérience. Le service
     * calcule l'éligibilité au remboursement (délai avant départ) ; le
     * remboursement effectif via PayTech est déclenché en B14.
     */
    public function cancel(Request $request, Booking $booking, ExperienceCancellationService $cancellation): JsonResponse
    {
        // Cette route ne concerne que les réservations d'expériences.
        if ($booking->bookable_type !== TourismExperience::class) {
            abort(404);
        }

        // Seul le titulaire de la réservation peut l'annuler.
        if ($booking->user_id !== $request->user()->id) {
            abort(403);
        }

        // Une réservation déjà annulée ne peut pas l'être à nouveau.
        if ($booking->status->estAnnulee()) {
            throw ValidationException::withMessages([
                'status' => ['Cette réservation est déjà annulée.'],
            ]);
        }

        $result = $cancellation->cancelByClient($booking);

        activity()->causedBy($request->user())->performedOn($booking)
            ->withProperties($result)->log('Annulation de réservation d\'expérience');

        return ApiResponse::success([
            'booking' => BookingResource::make($booking->fresh()),
            'refund' => [
                'eligible' => $result['refund_eligible'],
                'amount_xof' => $result['refund_amount_xof'],
            ],
        ]);
    }
}
