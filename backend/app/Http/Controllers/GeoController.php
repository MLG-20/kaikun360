<?php

namespace App\Http\Controllers;

use App\Models\Commune;
use App\Models\Department;
use App\Models\Region;
use App\Modules\Admin\Http\Requests\StoreCommuneRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Référentiel géographique public (F2.7.0).
 *
 * Expose les régions, départements et communes du Sénégal, afin que le
 * frontend puisse construire des sélecteurs EN CASCADE (région → département
 * → commune) pour le dépôt/l'édition d'un bien, qui exige des identifiants
 * géographiques valides et cohérents entre eux.
 *
 * Ces listes forment un référentiel de fond (14 régions, 46 départements) :
 * la lecture est publique et non paginée (petits volumes). Les communes, elles,
 * sont **extensibles** depuis F5.7 : le JSON ANSD qui les a amorcées est
 * volontairement incomplet, tout utilisateur connecté peut en proposer une
 * (`storeCommune()`), sans modération — voir son commentaire pour le pourquoi.
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

    /**
     * Propose une commune absente du référentiel. POST /api/v1/communes
     *
     * ⚠️ **Pas de modération** : la commune entre directement dans le
     * référentiel partagé, visible aussitôt de tout utilisateur qui liste les
     * communes de ce département. C'est une donnée géographique factuelle, pas
     * un contenu public à valider avant diffusion — une commune mal
     * orthographiée reste corrigeable depuis l'écran Référentiels du
     * back-office (`AdminGeoController`, CRUD déjà en place). Réutilise SA
     * validation (`StoreCommuneRequest`) telle quelle : mêmes règles
     * (département existant, nom unique dans ce département), aucune ne
     * dépend d'être montée sous une route admin.
     */
    public function storeCommune(StoreCommuneRequest $request): JsonResponse
    {
        $commune = Commune::create($request->validated());

        activity()->causedBy($request->user())->performedOn($commune)
            ->log("Commune « {$commune->name} » proposée par un utilisateur");

        return ApiResponse::created($commune->only(['id', 'department_id', 'name', 'type']));
    }
}
