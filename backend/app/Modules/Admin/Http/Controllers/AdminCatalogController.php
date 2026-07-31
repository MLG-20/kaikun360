<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Modules\Admin\Http\Resources\AdminExperienceResource;
use App\Modules\Admin\Http\Resources\AdminMobilityServiceResource;
use App\Modules\Admin\Http\Resources\AdminVehicleResource;
use App\Modules\Explore\Enums\ExperienceStatus;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Immo\Http\Resources\PropertyResource;
use App\Modules\Immo\Models\Property;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Models\Vehicle;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Navigateur back-office des catalogues (B13.7.1).
 *
 * Contrairement aux catalogues publics (limités aux ressources publiées), ces
 * vues exposent TOUS les statuts pour la supervision par les agents/admins.
 * Réservé à `consulter:dashboard-admin` (appliqué sur les routes). Les Resources
 * des modules sont réutilisées pour un format identique au reste de l'API.
 */
class AdminCatalogController extends Controller
{
    /**
     * Nombre d'éléments par page, borné.
     */
    private function perPage(Request $request): int
    {
        return max(1, min(100, (int) $request->integer('per_page', 20)));
    }

    /**
     * Biens (tous statuts). GET /api/v1/admin/properties
     */
    public function properties(Request $request): AnonymousResourceCollection
    {
        $properties = Property::query()
            ->with(['region', 'department', 'commune', 'owner'])
            // F8.1 — compteur de médias : repérer d'un coup d'œil une annonce
            // publiée SANS photo, ou dont des visuels ont été masqués.
            ->withCount(['allMedia as media_count', 'allMedia as media_hidden_count' => fn ($q) => $q->where('status', 'masque')])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->when($request->filled('region_id'), fn ($q) => $q->where('region_id', $request->integer('region_id')))
            ->when($request->filled('owner_id'), fn ($q) => $q->where('owner_id', $request->integer('owner_id')))
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q')->toString().'%'))
            ->latest()
            ->paginate($this->perPage($request));

        return PropertyResource::collection($properties);
    }

    /**
     * Véhicules (tous statuts). GET /api/v1/admin/vehicles
     *
     * Sert à la fois l'écran Catalogues (F7.2.b, vue synthétique) et l'onglet
     * « Flotte » de l'écran Mobilité (F7.2.j, vue de conformité) — d'où
     * `AdminVehicleResource`, sur-ensemble du format public incluant assurance,
     * chauffeur et drapeaux de conformité.
     *
     * Filtre supplémentaire `driver` (F7.2.j) : `1` = véhicules AVEC chauffeur,
     * `0` = sans. Le cahier des charges traite la mise à disposition de
     * chauffeurs comme une catégorie d'offre à part entière.
     */
    public function vehicles(Request $request): AnonymousResourceCollection
    {
        $vehicles = Vehicle::query()
            ->with('provider')
            // F8.1 — voir plus haut : compteur de médias pour la supervision.
            ->withCount(['allMedia as media_count', 'allMedia as media_hidden_count' => fn ($q) => $q->where('status', 'masque')])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->when($request->filled('provider_id'), fn ($q) => $q->where('provider_id', $request->integer('provider_id')))
            ->when($request->filled('driver'), fn ($q) => $q->where('has_driver', $request->boolean('driver')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(fn ($w) => $w->where('brand', 'like', $term)
                    ->orWhere('model', 'like', $term)
                    ->orWhere('reference', 'like', $term));
            })
            ->latest()
            ->paginate($this->perPage($request));

        return AdminVehicleResource::collection($vehicles);
    }

    /**
     * Trajets programmés (tous statuts). GET /api/v1/admin/mobility-services
     *
     * Onglet « Trajets » de l'écran Mobilité (F7.2.j). Contrairement à la
     * recherche publique (publiés uniquement, cache catalogue), on expose ici
     * tous les statuts et on agrège le **remplissage** de chaque trajet :
     * `seats_taken` = somme des participants des réservations non annulées,
     * calculée en une seule requête (`withSum`) pour éviter un N+1.
     *
     * Filtres : statut, type, période (`from`/`to` sur la date de départ) et
     * recherche libre sur départ / destination / référence.
     */
    public function mobilityServices(Request $request): AnonymousResourceCollection
    {
        $cancelled = $this->cancelledBookingStatuses();

        $services = MobilityService::query()
            ->with(['provider', 'vehicle'])
            ->withSum(
                ['bookings as seats_taken' => fn ($q) => $q->whereNotIn('status', $cancelled)],
                'guests'
            )
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->when($request->filled('provider_id'), fn ($q) => $q->where('provider_id', $request->integer('provider_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('departure_at', '>=', $request->string('from')->toString()))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('departure_at', '<=', $request->string('to')->toString()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(fn ($w) => $w->where('departure', 'like', $term)
                    ->orWhere('destination', 'like', $term)
                    ->orWhere('reference', 'like', $term));
            })
            ->orderByDesc('departure_at')
            ->paginate($this->perPage($request));

        return AdminMobilityServiceResource::collection($services);
    }

    /**
     * Circuits touristiques (tous statuts). GET /api/v1/admin/experiences
     *
     * Sert l'écran Catalogues (F7.2.b, vue synthétique) et l'onglet « Circuits »
     * de l'écran Tourisme (F7.2.k, vue d'exploitation) — d'où
     * `AdminExperienceResource`, sur-ensemble du format public incluant le
     * remplissage et le prestataire.
     *
     * ⚠️ Une expérience n'a pas de date de départ : sa capacité est un **total
     * par circuit** (B6.3). `seats_taken` cumule donc toutes ses réservations
     * non annulées, agrégées en une requête (`withSum`) pour éviter un N+1.
     *
     * Filtre supplémentaire `destination` (F7.2.k) : correspondance exacte, pour
     * croiser avec la vue par destination ci-dessous.
     */
    public function experiences(Request $request): AnonymousResourceCollection
    {
        $experiences = TourismExperience::query()
            ->with('provider')
            // F8.1 — voir plus haut : compteur de médias pour la supervision.
            ->withCount(['allMedia as media_count', 'allMedia as media_hidden_count' => fn ($q) => $q->where('status', 'masque')])
            ->withSum(
                ['bookings as seats_taken' => fn ($q) => $q->whereNotIn('status', $this->cancelledBookingStatuses())],
                'guests'
            )
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('provider_id'), fn ($q) => $q->where('provider_id', $request->integer('provider_id')))
            ->when($request->filled('destination'), fn ($q) => $q->where('destination', $request->string('destination')->toString()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(fn ($w) => $w->where('title', 'like', $term)
                    ->orWhere('destination', 'like', $term)
                    ->orWhere('reference', 'like', $term));
            })
            ->latest()
            ->paginate($this->perPage($request));

        return AdminExperienceResource::collection($experiences);
    }

    /**
     * Couverture touristique par destination. GET /api/v1/admin/tourism/destinations
     *
     * Onglet « Destinations » de l'écran Tourisme (F7.2.k). Le cahier des
     * charges (§6) demande de piloter les **destinations**, qui ne sont pas une
     * entité en base mais une **colonne** de `tourism_experiences` : on les
     * restitue donc par agrégation, ce qui répond à la vraie question de
     * l'équipe — quelles destinations sont couvertes, lesquelles n'ont que des
     * circuits en attente, lesquelles sont saturées.
     *
     * Une seule requête groupée (aucun N+1), non paginée : le nombre de
     * destinations distinctes reste de l'ordre de la dizaine. Le remplissage
     * est calculé à part (jointure sur les réservations non annulées) puis
     * recollé en mémoire, pour ne pas fausser les COUNT par la jointure.
     */
    public function tourismDestinations(Request $request): JsonResponse
    {
        $published = ExperienceStatus::PUBLIE->value;
        $pending = ExperienceStatus::EN_ATTENTE_VALIDATION->value;

        $rows = TourismExperience::query()
            ->selectRaw('destination')
            ->selectRaw('COUNT(*) as circuits_count')
            ->selectRaw('SUM(status = ?) as published_count', [$published])
            ->selectRaw('SUM(status = ?) as pending_count', [$pending])
            ->selectRaw('SUM(capacity) as capacity_total')
            ->selectRaw('MIN(price_xof) as price_min')
            ->selectRaw('MAX(price_xof) as price_max')
            ->when($request->filled('q'), fn ($q) => $q->where('destination', 'like', '%'.$request->string('q')->toString().'%'))
            ->groupBy('destination')
            ->orderByDesc('circuits_count')
            ->get();

        // Places occupées par destination, en une requête distincte : agrégée
        // dans la même requête, la jointure sur `bookings` multiplierait les
        // lignes et gonflerait COUNT(*) / SUM(capacity).
        $taken = Booking::query()
            ->where('bookable_type', TourismExperience::class)
            ->whereNotIn('bookings.status', $this->cancelledBookingStatuses())
            ->join('tourism_experiences', 'tourism_experiences.id', '=', 'bookings.bookable_id')
            ->groupBy('tourism_experiences.destination')
            ->selectRaw('tourism_experiences.destination as destination, SUM(bookings.guests) as seats')
            ->pluck('seats', 'destination');

        $destinations = $rows->map(function ($row) use ($taken) {
            $capacity = (int) $row->capacity_total;
            $seatsTaken = (int) ($taken[$row->destination] ?? 0);

            return [
                'destination' => $row->destination,
                'circuits_count' => (int) $row->circuits_count,
                'published_count' => (int) $row->published_count,
                'pending_count' => (int) $row->pending_count,
                'capacity_total' => $capacity,
                'seats_taken' => $seatsTaken,
                'seats_left' => max(0, $capacity - $seatsTaken),
                'price_min' => (int) $row->price_min,
                'price_max' => (int) $row->price_max,
            ];
        });

        return ApiResponse::success(['destinations' => $destinations]);
    }

    /**
     * Statuts de réservation valant annulation : ils ne consomment aucune place.
     *
     * Dérivés de l'enum (source de vérité unique) plutôt que recopiés, pour que
     * l'ajout d'un motif d'annulation n'oublie aucun décompte.
     *
     * @return array<int, string>
     */
    private function cancelledBookingStatuses(): array
    {
        return array_map(
            fn (BookingStatus $s) => $s->value,
            array_filter(BookingStatus::cases(), fn (BookingStatus $s) => $s->estAnnulee()),
        );
    }
}
