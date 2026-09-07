<?php

namespace App\Http\Controllers;

use App\Http\Resources\VehicleShowcaseCardResource;
use App\Models\VehicleShowcaseCard;
use App\Support\ApiResponse;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;

/**
 * Section « Location de véhicules » de la page d'accueil — lecture publique.
 *
 * Cartes publiées uniquement, dans l'ordre choisi au back-office. Le
 * regroupement par catégorie (berline, 4x4, minibus…) se fait côté frontend.
 */
class VehicleShowcaseController extends Controller
{
    /**
     * GET /api/v1/vehicle-showcase-cards (public).
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success([
            'cards' => VehicleShowcaseCardResource::collection(
                VehicleShowcaseCard::published()->ordered()->get()
            ),
            // Accroche de la section, pilotable au back-office (réglage
            // `home.vehicle_showcase_*`) — voir SettingsRepository::DEFAULTS
            // pour le texte par défaut.
            'eyebrow' => Settings::get('home.vehicle_showcase_eyebrow'),
            'title' => Settings::get('home.vehicle_showcase_title'),
            'lead' => Settings::get('home.vehicle_showcase_lead'),
        ]);
    }
}
