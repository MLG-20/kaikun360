<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\DashboardAggregator;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Tableau de bord du back-office (B13.1).
 *
 * Point d'entrée unique agrégeant les indicateurs de pilotage. L'accès est
 * réservé aux profils back-office via la permission `consulter:dashboard-admin`
 * (appliquée au niveau de la route).
 */
class AdminDashboardController extends Controller
{
    /**
     * Photographie des indicateurs. GET /api/v1/admin/dashboard
     */
    public function show(DashboardAggregator $aggregator): JsonResponse
    {
        return ApiResponse::success($aggregator->snapshot());
    }
}
