<?php

namespace App\Modules\Mobility\Http\Controllers;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Modules\Mobility\Http\Requests\StoreMobilityServiceBookingRequest;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Services\CommissionCalculator;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Réservation d'un service de mobilité (B7.4).
 *
 * Contrôle la capacité (places restantes) et fige la commission plateforme.
 */
class MobilityServiceBookingController extends Controller
{
    public function __construct(private readonly CommissionCalculator $commissions)
    {
    }

    /**
     * Réserve des places. POST /api/v1/mobility-services/{id}/bookings
     */
    public function store(StoreMobilityServiceBookingRequest $request, string $id): JsonResponse
    {
        $service = MobilityService::query()->published()->findOrFail($id);
        $guests = (int) $request->validated()['guests'];

        // Places restantes = capacité − participants des réservations non annulées.
        $taken = (int) $service->bookings()
            ->whereNotIn('status', $this->cancelledStatuses())
            ->sum('guests');
        $left = max(0, $service->capacity - $taken);

        if ($guests > $left) {
            throw ValidationException::withMessages([
                'guests' => ["Il ne reste que {$left} place(s) disponible(s)."],
            ]);
        }

        $amount = $guests * $service->price_xof;

        $booking = $service->bookings()->create([
            'reference' => 'BK-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
            'user_id' => $request->user()->id,
            'start_date' => optional($service->departure_at)->toDateString() ?? now()->toDateString(),
            'end_date' => optional($service->departure_at)->toDateString() ?? now()->toDateString(),
            'guests' => $guests,
            'amount_xof' => $amount,
            'commission_xof' => $this->commissions->commissionFor($amount),
            'status' => BookingStatus::EN_ATTENTE->value,
        ]);

        activity()->causedBy($request->user())->performedOn($booking)->log('Réservation de service de mobilité');

        return ApiResponse::created(['booking' => BookingResource::make($booking)]);
    }

    /**
     * @return array<int, string>
     */
    private function cancelledStatuses(): array
    {
        return array_map(
            fn (BookingStatus $s) => $s->value,
            array_filter(BookingStatus::cases(), fn (BookingStatus $s) => $s->estAnnulee()),
        );
    }
}
