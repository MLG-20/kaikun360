<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Modules\Admin\Http\Resources\AdminExperienceResource;
use App\Modules\Admin\Http\Resources\AdminMobilityServiceResource;
use App\Modules\Admin\Http\Resources\AdminVehicleResource;
use App\Modules\Admin\Validation\MediaEntry;
use App\Modules\Explore\Enums\ExperienceStatus;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Immo\Http\Resources\PropertyResource;
use App\Modules\Immo\Models\Property;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Models\Vehicle;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

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
     * Fiche d'un véhicule. GET /api/v1/admin/vehicles/{vehicle}
     *
     * **F8.2.b — pourquoi une fiche.** L'onglet Flotte signale qu'un véhicule
     * n'est pas conforme ; il ne dit pas quoi en faire. Or la décision dépend de
     * ce que la ligne ne montre pas : ce véhicule roule-t-il *déjà* (locations en
     * cours, départs programmés à venir) ? qui appeler ? à quoi ressemble-t-il ?
     *
     * La fiche répond en rassemblant : le **contrôle de conformité** pièce par
     * pièce (la grille dépend du moyen de transport — pirogue ou motorisé), le
     * **prestataire** joignable, les **photos**, les **locations** passées et à
     * venir, les **départs programmés** qui l'utilisent, et le **journal**.
     *
     * L'engagement à venir est le vrai enjeu : suspendre un véhicule qui porte
     * trois départs pleins n'est pas le même geste que suspendre un véhicule au
     * repos. Lecture seule, comme tout cet écran : la décision de publication
     * reste à la file de validation, qui la trace.
     */
    public function vehicle(Vehicle $vehicle): JsonResponse
    {
        $vehicle->load(['provider', 'allMedia'])
            ->loadCount(['allMedia as media_count', 'allMedia as media_hidden_count' => fn ($q) => $q->where('status', 'masque')]);

        $cancelled = $this->cancelledBookingStatuses();

        // Locations de ce véhicule : les 20 dernières, le client avec.
        $bookings = Booking::query()
            ->where('bookable_type', Vehicle::class)
            ->where('bookable_id', $vehicle->id)
            ->with('user:id,name,email,phone')
            ->orderByDesc('start_date')
            ->limit(20)
            ->get()
            ->map(fn (Booking $b) => [
                'booking_id' => $b->id,
                'reference' => $b->reference,
                'client_name' => $b->user?->name,
                'start_date' => $b->start_date?->toDateString(),
                'end_date' => $b->end_date?->toDateString(),
                'guests' => $b->guests,
                'amount_xof' => $b->amount_xof,
                'status' => $b->status->value,
            ]);

        // Départs programmés portés par ce véhicule, avec leur remplissage : ce
        // que l'équipe engagerait à annuler en cas de suspension.
        $trips = MobilityService::query()
            ->where('vehicle_id', $vehicle->id)
            ->withSum(
                ['bookings as seats_taken' => fn ($q) => $q->whereNotIn('status', $cancelled)],
                'guests'
            )
            ->orderByDesc('departure_at')
            ->limit(20)
            ->get()
            ->map(fn (MobilityService $s) => [
                'id' => $s->id,
                'reference' => $s->reference,
                'departure' => $s->departure,
                'destination' => $s->destination,
                'departure_at' => $s->departure_at?->toIso8601String(),
                'capacity' => (int) $s->capacity,
                'seats_taken' => (int) ($s->seats_taken ?? 0),
                'seats_left' => max(0, (int) $s->capacity - (int) ($s->seats_taken ?? 0)),
                'status' => $s->status?->value,
                'status_label' => $s->status?->label(),
                // Un départ passé n'engage plus rien : la fiche le signale pour
                // que l'agent ne compte que ce qui est encore devant lui.
                'is_upcoming' => $s->departure_at !== null && $s->departure_at->isFuture(),
            ]);

        return ApiResponse::success([
            'vehicle' => AdminVehicleResource::make($vehicle),
            'media' => MediaEntry::summary($vehicle),
            'bookings' => $bookings,
            'trips' => $trips,
            'activity' => $this->activityOf($vehicle),
        ]);
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
     * Fiche d'un trajet programmé. GET /api/v1/admin/mobility-services/{service}
     *
     * **F8.2.b.** La liste donne le remplissage d'un départ (« 12 / 15 ») ; la
     * fiche donne **qui** sont ces douze. C'est la différence entre superviser et
     * exploiter : un départ qui approche se prépare avec la liste des passagers
     * en main — noms, places, joignabilité, et qui n'a pas fini de payer.
     *
     * S'y ajoutent le **véhicule affecté** (avec sa capacité, pour repérer une
     * surréservation) et le **prestataire** opérateur. Les réservations annulées
     * sont écartées du décompte mais **restent listées, marquées comme telles** :
     * une annulation de la veille explique un départ soudain à moitié vide.
     */
    public function mobilityService(MobilityService $service): JsonResponse
    {
        $service->load(['provider', 'vehicle'])
            ->loadSum(
                ['bookings as seats_taken' => fn ($q) => $q->whereNotIn('status', $this->cancelledBookingStatuses())],
                'guests'
            );

        $passengers = $service->bookings()
            ->with('user:id,name,email,phone')
            ->latest()
            ->get()
            ->map(fn (Booking $b) => [
                'booking_id' => $b->id,
                'reference' => $b->reference,
                'client_name' => $b->user?->name,
                'client_email' => $b->user?->email,
                'client_phone' => $b->user?->phone,
                'guests' => $b->guests,
                'amount_xof' => $b->amount_xof,
                'paid_xof' => $b->montantPaye(),
                'remaining_xof' => $b->resteAPayer(),
                'status' => $b->status->value,
                'is_cancelled' => $b->status->estAnnulee(),
                'created_at' => $b->created_at?->toIso8601String(),
            ]);

        return ApiResponse::success([
            'trip' => AdminMobilityServiceResource::make($service),
            'passengers' => $passengers,
            'activity' => $this->activityOf($service),
        ]);
    }

    /**
     * Fiche d'un circuit touristique. GET /api/v1/admin/experiences/{experience}
     *
     * **F8.2.c.** L'onglet Circuits dit qu'un circuit est rempli à 12/15 ; il ne
     * dit pas **qui part**, ni ce que le circuit promet. Or les deux vont
     * ensemble : un circuit qui annonce « guide francophone + déjeuner » engage
     * la plateforme auprès de douze personnes nommées.
     *
     * La fiche réunit donc le **programme** (les inclusions, telles que le
     * prestataire les a déclarées), le **prestataire** joignable, les **photos**,
     * et la liste des **participants** — avec ce que chacun doit encore.
     *
     * ⚠️ Une expérience n'a **pas de date de départ** (B6.3) : sa capacité est un
     * total par circuit, et `seats_taken` cumule toutes ses réservations non
     * annulées. La fiche affiche donc un remplissage global, pas un départ daté.
     */
    public function experience(TourismExperience $experience): JsonResponse
    {
        $cancelled = $this->cancelledBookingStatuses();

        $experience->load(['provider', 'allMedia'])
            ->loadCount(['allMedia as media_count', 'allMedia as media_hidden_count' => fn ($q) => $q->where('status', 'masque')])
            ->loadSum(
                ['bookings as seats_taken' => fn ($q) => $q->whereNotIn('status', $cancelled)],
                'guests'
            );

        $participants = $experience->bookings()
            ->with('user:id,name,email,phone')
            ->latest()
            ->get()
            ->map(fn (Booking $b) => [
                'booking_id' => $b->id,
                'reference' => $b->reference,
                'client_name' => $b->user?->name,
                'client_email' => $b->user?->email,
                'client_phone' => $b->user?->phone,
                'guests' => $b->guests,
                'start_date' => $b->start_date?->toDateString(),
                'amount_xof' => $b->amount_xof,
                'paid_xof' => $b->montantPaye(),
                'remaining_xof' => $b->resteAPayer(),
                'status' => $b->status->value,
                'is_cancelled' => $b->status->estAnnulee(),
            ]);

        return ApiResponse::success([
            'experience' => AdminExperienceResource::make($experience),
            'media' => MediaEntry::summary($experience),
            'participants' => $participants,
            'activity' => $this->activityOf($experience),
        ]);
    }

    /**
     * Les 30 dernières entrées du journal d'audit portant sur un modèle (F8.2.b).
     *
     * Mutualisé entre les fiches : une décision tracée (suspension, correction)
     * doit rester lisible là où l'on consulte la ressource concernée.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function activityOf(Model $model): Collection
    {
        return Activity::query()
            ->where('subject_type', $model->getMorphClass())
            ->where('subject_id', $model->getKey())
            ->with('causer')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (Activity $entry) => [
                'id' => $entry->id,
                'description' => $entry->description,
                'causer_name' => $entry->causer?->name,
                'properties' => $entry->properties,
                'created_at' => $entry->created_at,
            ]);
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
