<?php

namespace App\Modules\Admin\Services;

use App\Enums\BookingStatus;
use App\Enums\ReviewStatus;
use App\Models\Booking;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Modules\Explore\Enums\ExperienceStatus;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Immo\Enums\PropertyStatus;
use App\Modules\Immo\Models\Property;
use App\Modules\Manage\Enums\IncidentStatus;
use App\Modules\Manage\Models\Incident;
use App\Modules\Mobility\Enums\VehicleStatus;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Pro\Enums\ProviderStatus;
use App\Modules\Pro\Models\Provider;

/**
 * Agrégateur des indicateurs du tableau de bord back-office (B13.1).
 *
 * Rassemble en un seul appel les chiffres dont un agent/administrateur a besoin
 * pour piloter la plateforme au quotidien :
 *   - files d'attente de validation (biens, véhicules, circuits, prestataires) ;
 *   - activité du jour (demandes reçues, réservations créées) ;
 *   - revenus estimés (volume transactionnel + commission plateforme) ;
 *   - alertes (avis à modérer, incidents ouverts) ;
 *   - KPI cumulés (utilisateurs, prestataires validés, biens publiés, réservations).
 *
 * Chaque indicateur est une requête d'agrégation simple (COUNT/SUM), sans
 * chargement de collection : le dashboard reste léger même à volume élevé.
 */
class DashboardAggregator
{
    /**
     * Construit la photographie complète du back-office.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'queues' => $this->validationQueues(),
            'today' => $this->todayActivity(),
            'revenue' => $this->revenue(),
            'alerts' => $this->alerts(),
            'kpi' => $this->kpi(),
        ];
    }

    /**
     * Volumes en attente de validation, par type de ressource. Ces compteurs
     * pilotent la file de validation détaillée (B13.2).
     *
     * @return array<string, int>
     */
    private function validationQueues(): array
    {
        return [
            'properties_pending' => Property::where('status', PropertyStatus::EN_ATTENTE_VALIDATION->value)->count(),
            'vehicles_pending' => Vehicle::where('status', VehicleStatus::EN_ATTENTE_VALIDATION->value)->count(),
            'experiences_pending' => TourismExperience::where('status', ExperienceStatus::EN_ATTENTE_VALIDATION->value)->count(),
            'providers_pending' => Provider::where('status', ProviderStatus::EN_ATTENTE->value)->count(),
        ];
    }

    /**
     * Activité du jour (calendaire). Utilise `whereDate` pour borner à la date
     * courante du serveur.
     *
     * @return array<string, int>
     */
    private function todayActivity(): array
    {
        return [
            'requests' => ServiceRequest::whereDate('created_at', today())->count(),
            'bookings' => Booking::whereDate('created_at', today())->count(),
        ];
    }

    /**
     * Revenus estimés à partir des réservations NON annulées :
     *   - `gross_volume_xof` = volume transactionnel encaissable ;
     *   - `commission_xof`   = part revenant à la plateforme.
     *
     * Estimation : le montant réellement encaissé dépend de PayTech (B14).
     *
     * @return array<string, int>
     */
    private function revenue(): array
    {
        $active = Booking::whereNotIn('status', $this->cancelledStatuses());

        return [
            'gross_volume_xof' => (int) (clone $active)->sum('amount_xof'),
            'commission_xof' => (int) (clone $active)->sum('commission_xof'),
        ];
    }

    /**
     * Signaux nécessitant une action humaine.
     *
     * @return array<string, int>
     */
    private function alerts(): array
    {
        return [
            'reviews_to_moderate' => Review::where('status', ReviewStatus::EN_ATTENTE->value)->count(),
            'open_incidents' => Incident::where('status', IncidentStatus::OUVERT->value)->count(),
        ];
    }

    /**
     * Indicateurs cumulés de santé de la plateforme.
     *
     * @return array<string, int>
     */
    private function kpi(): array
    {
        return [
            'users_total' => User::count(),
            'providers_validated' => Provider::where('status', ProviderStatus::VALIDE->value)->count(),
            'properties_published' => Property::where('status', PropertyStatus::PUBLIE->value)->count(),
            'bookings_total' => Booking::count(),
        ];
    }

    /**
     * Valeurs de statut correspondant à une annulation (exclues des revenus).
     *
     * @return array<int, string>
     */
    private function cancelledStatuses(): array
    {
        return array_values(array_map(
            fn (BookingStatus $s) => $s->value,
            array_filter(BookingStatus::cases(), fn (BookingStatus $s) => $s->estAnnulee()),
        ));
    }
}
