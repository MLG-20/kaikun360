<?php

namespace App\Modules\Mobility\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\CautionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Modules\Mobility\Http\Requests\StoreVehicleBookingRequest;
use App\Modules\Mobility\Models\Vehicle;
use App\Support\Billing\CommissionCalculator;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Location de véhicule : réservation (caution + commission) et annulation (B7.4).
 *
 * La caution est « retenue » à la réservation. À l'annulation, elle est
 * « restituée » si l'annulation est conforme (≥ DELAI jours avant le départ),
 * sinon « perdue ». Le remboursement effectif via PayTech est câblé en B14.
 */
class VehicleBookingController extends Controller
{
    /**
     * Délai minimal (jours) avant le départ pour une annulation conforme.
     */
    public const CANCEL_DELAY_DAYS = 2;

    public function __construct(private readonly CommissionCalculator $commissions)
    {
    }

    /**
     * Réserve un véhicule. POST /api/v1/vehicles/{id}/bookings
     */
    public function store(StoreVehicleBookingRequest $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::query()->published()->findOrFail($id);
        $data = $request->validated();

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        // Au moins une journée de location facturée.
        $days = max(1, $start->diffInDays($end));

        $amount = $days * $vehicle->price_per_day_xof;
        $caution = (int) $vehicle->caution_xof;

        $booking = $vehicle->bookings()->create([
            'reference' => 'BK-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
            'user_id' => $request->user()->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'guests' => $data['guests'] ?? 1,
            'amount_xof' => $amount,
            'commission_xof' => $this->commissions->commissionFor($amount),
            'caution_xof' => $caution,
            'caution_status' => $caution > 0 ? CautionStatus::RETENUE->value : null,
            'status' => BookingStatus::EN_ATTENTE->value, // en attente de paiement (B14)
        ]);

        activity()->causedBy($request->user())->performedOn($booking)->log('Location de véhicule');

        return ApiResponse::created(['booking' => BookingResource::make($booking)]);
    }

    /**
     * Annule une location. PATCH /api/v1/vehicles/bookings/{booking}/cancel
     *
     * Annulation conforme (assez tôt) → caution restituée + remboursement
     * éligible (PayTech en B14) ; sinon caution perdue, pas de remboursement.
     */
    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        if ($booking->bookable_type !== Vehicle::class) {
            abort(404);
        }
        if ($booking->user_id !== $request->user()->id) {
            abort(403);
        }
        if ($booking->status->estAnnulee()) {
            throw ValidationException::withMessages([
                'status' => ['Cette location est déjà annulée.'],
            ]);
        }

        $daysBefore = Carbon::today()->diffInDays(Carbon::parse($booking->start_date), false);
        $conforme = $daysBefore >= self::CANCEL_DELAY_DAYS;

        $hadCaution = $booking->caution_status === CautionStatus::RETENUE;

        $booking->update([
            'status' => BookingStatus::ANNULEE_CLIENT->value,
            'caution_status' => $hadCaution
                ? ($conforme ? CautionStatus::RESTITUEE->value : CautionStatus::PERDUE->value)
                : $booking->caution_status,
        ]);

        $refundEligible = $conforme;
        $refundAmount = $conforme ? (int) $booking->amount_xof : 0;

        activity()->causedBy($request->user())->performedOn($booking)
            ->withProperties([
                'conforme' => $conforme,
                'refund_amount_xof' => $refundAmount,
            ])
            ->log('Annulation de location de véhicule');

        return ApiResponse::success([
            'booking' => BookingResource::make($booking->fresh()),
            'refund' => [
                'eligible' => $refundEligible,
                'amount_xof' => $refundAmount,
            ],
        ]);
    }
}
