<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Build\Http\Resources\ConstructionRequestResource;
use App\Modules\Build\Models\ConstructionRequest;
use App\Modules\Manage\Http\Resources\MandateResource;
use App\Modules\Manage\Models\ManagementMandate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Vues back-office des dossiers de suivi (B13.7.2).
 *
 * Listes de supervision transverses (tous clients / tous propriétaires) pour la
 * construction et la gestion locative, qui ne disposaient que d'accès « les
 * miens » côté module. Réservé à `consulter:dashboard-admin`. Team building et
 * diaspora exposent déjà leur file back-office dans leurs modules respectifs.
 */
class AdminDossierController extends Controller
{
    private function perPage(Request $request): int
    {
        return max(1, min(100, (int) $request->integer('per_page', 20)));
    }

    /**
     * Demandes de construction (toutes). GET /api/v1/admin/construction-requests
     */
    public function constructionRequests(Request $request): AnonymousResourceCollection
    {
        $requests = ConstructionRequest::query()
            ->withCount(['reports', 'milestones'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('city'), fn ($q) => $q->where('city', $request->string('city')->toString()))
            ->latest()
            ->paginate($this->perPage($request));

        return ConstructionRequestResource::collection($requests);
    }

    /**
     * Mandats de gestion locative (tous). GET /api/v1/admin/mandates
     */
    public function mandates(Request $request): AnonymousResourceCollection
    {
        $mandates = ManagementMandate::query()
            ->with(['property.region', 'property.department', 'property.commune', 'property.owner', 'owner'])
            ->withCount(['rents', 'incidents', 'expenses', 'payouts'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('owner_id'), fn ($q) => $q->where('owner_id', $request->integer('owner_id')))
            ->latest()
            ->paginate($this->perPage($request));

        return MandateResource::collection($mandates);
    }
}
