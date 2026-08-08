<?php

namespace App\Modules\Mobility\Http\Controllers;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Modules\Mobility\Http\Requests\StoreMobilityServiceBookingRequest;
use App\Modules\Mobility\Models\MobilityService;
use App\Support\ApiResponse;
use App\Support\Billing\CommissionCalculator;
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
    public function __construct(private readonly CommissionCalculator $commissions) {}

    /**
     * Réserve des places. POST /api/v1/mobility-services/{id}/bookings
     */
    public function store(StoreMobilityServiceBookingRequest $request, string $id): JsonResponse
    {
        $service = MobilityService::query()->published()->findOrFail($id);
        $guests = (int) $request->validated()['guests'];

        // F8.23.a — ON NE VEND PAS UNE PLACE DANS UN CAR DÉJÀ PARTI.
        //
        // ⚠️ Ce contrôle manquait depuis B7.4, et le défaut était monnayable :
        // éprouvé sur le serveur réel, une réservation de 75 128 F a été acceptée
        // sur un départ parti trois semaines plus tôt — avec une `start_date`
        // dans le passé, une commission calculée, et un client à rembourser.
        //
        // ⚠️ La fiche affichait pourtant « ce départ a déjà eu lieu » depuis
        // F8.10 : elle masquait le bouton, elle ne fermait pas la route. Un
        // écran ne protège rien ; il ne fait qu'éviter de proposer la faute.
        //
        // `departure_at` nullable = service à la demande, sans échéance : rien
        // à refuser dans ce cas (cf. `scopeAVenir()`).
        if ($service->departure_at !== null && $service->departure_at->isPast()) {
            throw ValidationException::withMessages([
                'departure_at' => ['Ce départ a déjà eu lieu : il n\'est plus réservable.'],
            ]);
        }

        // Places restantes = capacité − participants des réservations non annulées.
        $taken = (int) $service->bookings()
            ->whereNotIn('status', BookingStatus::valeursAnnulees())
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
}
