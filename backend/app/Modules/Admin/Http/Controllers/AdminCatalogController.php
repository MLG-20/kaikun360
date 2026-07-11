<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Explore\Http\Resources\ExperienceResource;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Immo\Http\Resources\PropertyResource;
use App\Modules\Immo\Models\Property;
use App\Modules\Mobility\Http\Resources\VehicleResource;
use App\Modules\Mobility\Models\Vehicle;
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
     */
    public function vehicles(Request $request): AnonymousResourceCollection
    {
        $vehicles = Vehicle::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->when($request->filled('provider_id'), fn ($q) => $q->where('provider_id', $request->integer('provider_id')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(fn ($w) => $w->where('brand', 'like', $term)->orWhere('model', 'like', $term));
            })
            ->latest()
            ->paginate($this->perPage($request));

        return VehicleResource::collection($vehicles);
    }

    /**
     * Circuits touristiques (tous statuts). GET /api/v1/admin/experiences
     */
    public function experiences(Request $request): AnonymousResourceCollection
    {
        $experiences = TourismExperience::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('provider_id'), fn ($q) => $q->where('provider_id', $request->integer('provider_id')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('destination', 'like', $term));
            })
            ->latest()
            ->paginate($this->perPage($request));

        return ExperienceResource::collection($experiences);
    }
}
