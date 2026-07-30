<?php

namespace App\Modules\Stay\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\CautionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Modules\Stay\Http\Requests\StoreStayBookingRequest;
use App\Modules\Stay\Models\Stay;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Disponibilité et réservation d'une nuitée (phase B3.3).
 */
class StayBookingController extends Controller
{
    /**
     * Calendrier d'occupation. GET /api/v1/stays/{id}/availability
     *
     * Retourne les périodes déjà réservées (réservations non annulées), pour que
     * le frontend grise les dates indisponibles.
     */
    public function availability(string $id): JsonResponse
    {
        $stay = Stay::query()->bookable()->findOrFail($id);

        $booked = $stay->bookings()
            ->whereNotIn('status', $this->statutsAnnules())
            ->get(['start_date', 'end_date'])
            ->map(fn ($b) => [
                'start_date' => $b->start_date->toDateString(),
                'end_date' => $b->end_date->toDateString(),
            ]);

        return ApiResponse::success([
            'stay_id' => $stay->id,
            'booked' => $booked,
        ]);
    }

    /**
     * Réservation d'un créneau. POST /api/v1/stays/{id}/bookings
     *
     * Vérifie capacité, nombre de nuits et surtout l'ABSENCE DE CHEVAUCHEMENT
     * avec une réservation existante (pas de double réservation).
     */
    public function store(StoreStayBookingRequest $request, string $id): JsonResponse
    {
        $stay = Stay::query()->bookable()->findOrFail($id);
        $data = $request->validated();

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $nights = $start->diffInDays($end);

        // Règles dépendant de la nuitée.
        if ($data['guests'] > $stay->capacity) {
            throw ValidationException::withMessages([
                'guests' => ["La capacité maximale est de {$stay->capacity} personne(s)."],
            ]);
        }
        if ($nights < $stay->min_nights) {
            throw ValidationException::withMessages([
                'end_date' => ["Le séjour minimum est de {$stay->min_nights} nuit(s)."],
            ]);
        }
        if ($stay->max_nights !== null && $nights > $stay->max_nights) {
            throw ValidationException::withMessages([
                'end_date' => ["Le séjour maximum est de {$stay->max_nights} nuit(s)."],
            ]);
        }

        // Anti double-réservation : deux périodes [s1,e1) et [s2,e2) se chevauchent
        // si s1 < e2 ET s2 < e1 (la date de départ est exclusive).
        $chevauchement = $stay->bookings()
            ->whereNotIn('status', $this->statutsAnnules())
            ->where('start_date', '<', $end)
            ->where('end_date', '>', $start)
            ->exists();

        if ($chevauchement) {
            throw ValidationException::withMessages([
                'start_date' => ['Ce créneau est déjà réservé.'],
            ]);
        }

        $booking = $stay->bookings()->create([
            'reference' => $this->genererReference(),
            'user_id' => $request->user()->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'guests' => $data['guests'],
            'amount_xof' => $nights * $stay->price_per_night_xof,
            'caution_xof' => $stay->caution_xof,
            // F7.3.f — la caution était RECOPIÉE sans jamais être suivie : son
            // statut restait `null` pour une nuitée, là où la location de véhicule
            // le renseigne depuis B7.4. Sans cet état, impossible de savoir au
            // départ si la caution est encore due au client. Elle est retenue dès
            // la réservation (et `null` si le logement n'en demande pas).
            'caution_status' => $stay->caution_xof > 0 ? CautionStatus::RETENUE->value : null,
            'status' => BookingStatus::EN_ATTENTE->value, // en attente de paiement (B14)
        ]);

        activity()->causedBy($request->user())->performedOn($booking)->log('Réservation de nuitée');

        return ApiResponse::created([
            'booking' => BookingResource::make($booking),
        ]);
    }

    /**
     * Valeurs de statut correspondant à une annulation (à exclure des conflits).
     *
     * @return array<int, string>
     */
    private function statutsAnnules(): array
    {
        return array_map(
            fn (BookingStatus $s) => $s->value,
            array_filter(BookingStatus::cases(), fn (BookingStatus $s) => $s->estAnnulee()),
        );
    }

    /**
     * Référence unique et lisible d'une réservation.
     */
    private function genererReference(): string
    {
        return 'BK-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
    }
}
