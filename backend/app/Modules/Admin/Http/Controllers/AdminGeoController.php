<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Commune;
use App\Models\Department;
use App\Models\Region;
use App\Support\ApiResponse;
use App\Modules\Admin\Http\Requests\StoreCommuneRequest;
use App\Modules\Admin\Http\Requests\StoreDepartmentRequest;
use App\Modules\Admin\Http\Requests\UpdateCommuneRequest;
use App\Modules\Admin\Http\Requests\UpdateDepartmentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Administration du référentiel géographique — les « villes » du CDC §6
 * (module *Paramètres*), livrées en F7.2.l.
 *
 * Jusqu'ici ce référentiel (14 régions, 46 départements, ~557 communes) était
 * FIGÉ : semé par `SenegalGeographySeeder` / `CommunesSeeder` et exposé en
 * lecture seule par {@see \App\Http\Controllers\GeoController} pour alimenter
 * les sélecteurs en cascade du dépôt de bien. Le cahier des charges demande que
 * l'équipe puisse le maintenir elle-même (nouvelle commune, faute de frappe,
 * découpage administratif qui évolue) — d'où ce contrôleur d'écriture.
 *
 * Deux principes de sûreté, parce que ces données sont référencées ailleurs :
 *  1. **Les régions ne sont pas modifiables ici.** Les 14 régions sont une
 *     nomenclature nationale stable ; les créer/supprimer depuis une interface
 *     serait un risque disproportionné face au besoin réel.
 *  2. **Aucune suppression en cascade silencieuse.** `properties.commune_id` et
 *     `users.commune_id` sont en `nullOnDelete` : supprimer une commune utilisée
 *     effacerait la localisation des biens concernés SANS erreur. On refuse donc
 *     toute suppression d'un élément encore rattaché (409), en indiquant combien
 *     d'objets le retiennent.
 *
 * Réservé à la permission `gerer:parametres` (appliquée sur les routes).
 */
class AdminGeoController extends Controller
{
    /**
     * Arborescence complète du référentiel. GET /api/v1/admin/geography
     *
     * Renvoie les régions avec leurs départements et le nombre de communes de
     * chacun — de quoi construire l'arbre de navigation de l'écran Paramètres
     * en UN appel. Les communes elles-mêmes ne sont pas incluses (~557 lignes) :
     * elles sont chargées à la demande par {@see self::communes()}.
     */
    public function tree(): JsonResponse
    {
        $regions = Region::query()
            ->with(['departments' => fn ($query) => $query->withCount('communes')->orderBy('name')])
            ->withCount('departments')
            ->orderBy('name')
            ->get();

        return ApiResponse::success([
            'regions' => $regions->map(fn (Region $region) => [
                'id' => $region->id,
                'name' => $region->name,
                'departments_count' => $region->departments_count,
                // Total des communes de la région = somme de celles de ses
                // départements (déjà comptées ci-dessus, pas de requête de plus).
                'communes_count' => (int) $region->departments->sum('communes_count'),
                'departments' => $region->departments->map(fn (Department $department) => [
                    'id' => $department->id,
                    'region_id' => $department->region_id,
                    'name' => $department->name,
                    'communes_count' => $department->communes_count,
                ])->values(),
            ])->values(),
        ]);
    }

    /**
     * Communes d'un département (ou recherche transverse).
     * GET /api/v1/admin/communes?department_id=&region_id=&q=
     *
     * Chaque ligne porte son USAGE (biens + comptes rattachés) : c'est ce qui
     * permet à l'écran de griser le bouton « Supprimer » avant même de tenter
     * l'appel, et d'expliquer pourquoi.
     */
    public function communes(Request $request): JsonResponse
    {
        $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer'],
        ]);

        $perPage = max(1, min(200, (int) $request->integer('per_page', 50)));

        $communes = Commune::query()
            ->with('department.region')
            ->withCount(['properties', 'users'])
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            // Filtre par région : passe par le département parent (la commune ne
            // porte pas de `region_id` — la hiérarchie est stricte).
            ->when($request->filled('region_id'), fn ($query) => $query->whereHas(
                'department',
                fn ($sub) => $sub->where('region_id', $request->integer('region_id')),
            ))
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.$request->string('q')->toString().'%'))
            ->orderBy('name')
            ->paginate($perPage)
            ->through(fn (Commune $commune) => $this->communePayload($commune));

        return ApiResponse::paginated($communes);
    }

    /**
     * Crée une commune. POST /api/v1/admin/communes
     */
    public function storeCommune(StoreCommuneRequest $request): JsonResponse
    {
        $commune = Commune::create($request->validated());

        activity()->causedBy($request->user())->performedOn($commune)
            ->log("Création de la commune « {$commune->name} »");

        return ApiResponse::created([
            'commune' => $this->communePayload($commune->load('department.region')->loadCount(['properties', 'users'])),
        ]);
    }

    /**
     * Renomme ou reclasse une commune. PATCH /api/v1/admin/communes/{commune}
     */
    public function updateCommune(UpdateCommuneRequest $request, Commune $commune): JsonResponse
    {
        $before = $commune->name;

        $commune->update($request->validated());

        activity()->causedBy($request->user())->performedOn($commune)
            ->log("Modification de la commune « {$before} » → « {$commune->name} »");

        return ApiResponse::success([
            'commune' => $this->communePayload($commune->fresh()->load('department.region')->loadCount(['properties', 'users'])),
        ]);
    }

    /**
     * Supprime une commune INUTILISÉE. DELETE /api/v1/admin/communes/{commune}
     *
     * Refus explicite (409) si des biens ou des comptes y sont rattachés : la FK
     * étant en `nullOnDelete`, laisser passer reviendrait à perdre des données de
     * localisation sans le moindre signal.
     */
    public function destroyCommune(Request $request, Commune $commune): JsonResponse
    {
        $usage = $commune->properties()->count() + $commune->users()->count();

        if ($usage > 0) {
            return ApiResponse::error(
                "Suppression impossible : {$usage} élément(s) (biens ou comptes) sont encore rattachés à cette commune.",
                409,
            );
        }

        activity()->causedBy($request->user())->performedOn($commune)
            ->log("Suppression de la commune « {$commune->name} »");

        $commune->delete();

        return ApiResponse::noContent();
    }

    /**
     * Crée un département. POST /api/v1/admin/departments
     */
    public function storeDepartment(StoreDepartmentRequest $request): JsonResponse
    {
        $department = Department::create($request->validated());

        activity()->causedBy($request->user())->performedOn($department)
            ->log("Création du département « {$department->name} »");

        return ApiResponse::created([
            'department' => $this->departmentPayload($department->load('region')->loadCount('communes')),
        ]);
    }

    /**
     * Renomme ou rattache un département. PATCH /api/v1/admin/departments/{department}
     */
    public function updateDepartment(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $before = $department->name;

        $department->update($request->validated());

        activity()->causedBy($request->user())->performedOn($department)
            ->log("Modification du département « {$before} » → « {$department->name} »");

        return ApiResponse::success([
            'department' => $this->departmentPayload($department->fresh()->load('region')->loadCount('communes')),
        ]);
    }

    /**
     * Supprime un département VIDE et inutilisé.
     * DELETE /api/v1/admin/departments/{department}
     *
     * Garde-fou renforcé : la FK `communes.department_id` est en
     * `cascadeOnDelete` — supprimer un département emporterait TOUTES ses
     * communes d'un coup. On exige donc qu'il soit vide, en plus d'être
     * inutilisé par les biens et les comptes.
     */
    public function destroyDepartment(Request $request, Department $department): JsonResponse
    {
        $communes = $department->communes()->count();

        if ($communes > 0) {
            return ApiResponse::error(
                "Suppression impossible : ce département contient encore {$communes} commune(s). Supprimez-les d'abord.",
                409,
            );
        }

        $usage = $department->properties()->count() + $department->users()->count();

        if ($usage > 0) {
            return ApiResponse::error(
                "Suppression impossible : {$usage} élément(s) (biens ou comptes) sont encore rattachés à ce département.",
                409,
            );
        }

        activity()->causedBy($request->user())->performedOn($department)
            ->log("Suppression du département « {$department->name} »");

        $department->delete();

        return ApiResponse::noContent();
    }

    /**
     * Forme normalisée d'une commune pour le back-office.
     *
     * @return array<string, mixed>
     */
    private function communePayload(Commune $commune): array
    {
        $properties = (int) ($commune->properties_count ?? 0);
        $users = (int) ($commune->users_count ?? 0);

        return [
            'id' => $commune->id,
            'name' => $commune->name,
            'type' => $commune->type,
            'department_id' => $commune->department_id,
            'department_name' => $commune->department?->name,
            'region_id' => $commune->department?->region_id,
            'region_name' => $commune->department?->region?->name,
            'properties_count' => $properties,
            'users_count' => $users,
            // Calculé côté serveur : le front n'a pas à rejouer la règle.
            'deletable' => $properties + $users === 0,
        ];
    }

    /**
     * Forme normalisée d'un département pour le back-office.
     *
     * @return array<string, mixed>
     */
    private function departmentPayload(Department $department): array
    {
        return [
            'id' => $department->id,
            'name' => $department->name,
            'region_id' => $department->region_id,
            'region_name' => $department->region?->name,
            'communes_count' => (int) ($department->communes_count ?? 0),
        ];
    }
}
