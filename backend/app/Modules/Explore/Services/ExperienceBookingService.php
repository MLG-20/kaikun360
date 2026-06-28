<?php

namespace App\Modules\Explore\Services;

use App\Enums\BookingStatus;
use App\Modules\Explore\Models\TourismExperience;

/**
 * Logique de capacité d'une expérience (phase B6.3).
 *
 * Une expérience a une capacité totale (places du circuit). Les places restantes
 * = capacité − somme des participants des réservations NON annulées (le panier
 * groupe occupe plusieurs places via le champ `guests`).
 */
class ExperienceBookingService
{
    /**
     * Nombre de places déjà occupées (réservations non annulées).
     */
    public function seatsTaken(TourismExperience $experience): int
    {
        return (int) $experience->bookings()
            ->whereNotIn('status', $this->cancelledStatuses())
            ->sum('guests');
    }

    /**
     * Nombre de places restantes (jamais négatif).
     */
    public function seatsLeft(TourismExperience $experience): int
    {
        return max(0, $experience->capacity - $this->seatsTaken($experience));
    }

    /**
     * Indique si l'expérience peut accueillir `$guests` participants de plus.
     */
    public function canAccommodate(TourismExperience $experience, int $guests): bool
    {
        return $guests <= $this->seatsLeft($experience);
    }

    /**
     * Valeurs de statut correspondant à une annulation (exclues du décompte).
     *
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
