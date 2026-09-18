<?php

namespace App\Modules\Explore\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Explore\Models\TourismExperienceDeparture;

/**
 * Logique de capacité d'une expérience (phase B6.3, revue en F21).
 *
 * La capacité n'est plus globale au circuit : chaque DATE DE DÉPART a ses
 * propres places. Les places restantes d'une date = ses places totales − la
 * somme des participants des réservations non annulées portant cette même
 * date de départ (le panier groupe occupe plusieurs places via `guests`).
 *
 * ⚠️ Le rapprochement réservation ↔ date se fait par égalité de date, pas par
 * clé étrangère (cf. la migration de `tourism_experience_departures`).
 */
class ExperienceBookingService
{
    /**
     * Nombre de places déjà occupées sur cette date de départ.
     */
    public function seatsTaken(TourismExperienceDeparture $departure): int
    {
        return (int) Booking::query()
            ->where('bookable_type', TourismExperience::class)
            ->where('bookable_id', $departure->tourism_experience_id)
            ->whereDate('start_date', $departure->start_date)
            ->whereNotIn('status', BookingStatus::valeursAnnulees())
            ->sum('guests');
    }

    /**
     * Nombre de places restantes sur cette date (jamais négatif).
     */
    public function seatsLeft(TourismExperienceDeparture $departure): int
    {
        return max(0, $departure->seats_total - $this->seatsTaken($departure));
    }

    /**
     * Indique si cette date de départ peut accueillir `$guests` participants de plus.
     */
    public function canAccommodate(TourismExperienceDeparture $departure, int $guests): bool
    {
        return $guests <= $this->seatsLeft($departure);
    }
}
