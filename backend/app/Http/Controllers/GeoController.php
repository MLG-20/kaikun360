<?php

namespace App\Http\Controllers;

use App\Models\Commune;
use App\Models\Department;
use App\Models\Region;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Référentiel géographique public (F2.7.0).
 *
 * Expose en LECTURE SEULE les régions, départements et communes du Sénégal,
 * afin que le frontend puisse construire des sélecteurs EN CASCADE
 * (région → département → commune) pour le dépôt de bien (POST /properties),
 * qui exige des identifiants géographiques valides et cohérents entre eux.
 *
 * Ces listes sont un référentiel figé (14 régions, 46 départements, ~557
 * communes) : la lecture est publique et non paginée (petits volumes).
 */
class GeoController extends Controller
{
    /**
     * Liste des régions. GET /api/v1/regions
     */
    public function regions(): JsonResponse
    {
        $regions = Region::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return ApiResponse::success($regions);
    }

    /**
     * Liste des départements d'une région. GET /api/v1/departments?region_id=
     *
     * `region_id` est obligatoire : on ne renvoie jamais les 46 départements
     * d'un coup, le front demande toujours ceux d'une région choisie.
     */
    public function departments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'region_id' => ['required', 'integer', 'exists:regions,id'],
        ]);

        $departments = Department::query()
            ->where('region_id', $validated['region_id'])
            ->orderBy('name')
            ->get(['id', 'region_id', 'name']);

        return ApiResponse::success($departments);
    }

    /**
     * Liste des communes d'un département. GET /api/v1/communes?department_id=
     *
     * `department_id` obligatoire (même logique de cascade que ci-dessus).
     */
    public function communes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
        ]);

        $communes = Commune::query()
            ->where('department_id', $validated['department_id'])
            ->orderBy('name')
            ->get(['id', 'department_id', 'name', 'type']);

        return ApiResponse::success($communes);
    }
}
