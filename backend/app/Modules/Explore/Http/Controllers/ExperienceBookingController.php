<?php

namespace App\Modules\Explore\Http\Controllers;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Modules\Explore\Http\Requests\StoreExperienceBookingRequest;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Explore\Services\ExperienceBookingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
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
    public function __construct(private readonly ExperienceBookingService $capacity)
    {
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

        $booking = $experience->bookings()->create([
            'reference' => 'BK-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
            'user_id' => $request->user()->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'guests' => $guests,
            'amount_xof' => $guests * $experience->price_xof,
            'status' => BookingStatus::EN_ATTENTE->value, // en attente de paiement (B14)
        ]);

        activity()->causedBy($request->user())->performedOn($booking)->log('Réservation d\'expérience');

        return ApiResponse::created(['booking' => BookingResource::make($booking)]);
    }
}
