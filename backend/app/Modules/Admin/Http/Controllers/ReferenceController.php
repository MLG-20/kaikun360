<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Modules\Immo\Enums\PropertyType;
use App\Modules\Mobility\Enums\VehicleType;
use App\Modules\Pro\Enums\ProviderCategory;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Données de référence en LECTURE SEULE pour le back-office (B13.4).
 *
 * Expose les nomenclatures qui restent définies en dur côté domaine (catégories
 * = enums PHP) ainsi que le référentiel géographique (régions du Sénégal), pour
 * alimenter les listes déroulantes des écrans d'administration. Les rendre
 * pleinement éditables serait un chantier transversal distinct.
 */
class ReferenceController extends Controller
{
    /**
     * GET /api/v1/admin/reference
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success([
            'categories' => [
                'provider' => $this->fromEnum(ProviderCategory::cases()),
                'property_type' => $this->fromEnum(PropertyType::cases()),
                'service_type' => $this->fromEnum(ServiceType::cases()),
                'vehicle_type' => $this->fromEnum(VehicleType::cases()),
            ],
            'geography' => [
                'regions' => Region::query()->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    /**
     * Normalise une liste de cas d'enum en `[{ value, label }]`.
     *
     * @param  array<int, \BackedEnum>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    private function fromEnum(array $cases): array
    {
        return array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            $cases,
        );
    }
}
