<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Http\Requests\ValidateResourceRequest;
use App\Modules\Admin\Validation\ValidatorRegistry;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * File de validation et modération générique du back-office (B13.2).
 *
 * Un unique point d'entrée pilote la validation de TOUS les types de ressources
 * soumis à approbation (biens, véhicules, circuits, prestataires), en déléguant
 * à un ResourceValidator dédié (cf. ValidatorRegistry). Les effets de bord
 * métier (événements, conformité, synchronisation de profil) sont préservés.
 */
class ValidationQueueController extends Controller
{
    /**
     * Nombre d'éléments prévisualisés par type dans la vue d'ensemble.
     */
    private const PREVIEW = 15;

    /**
     * File de validation. GET /api/v1/admin/queue
     *
     * - Sans `?type` : vue d'ensemble (compteur + aperçu par type).
     * - Avec `?type=vehicle` : liste paginée normalisée d'un seul type.
     */
    public function index(Request $request, ValidatorRegistry $registry): JsonResponse
    {
        $type = $request->query('type');

        if ($type !== null) {
            $validator = $registry->for($type); // 404 si type inconnu
            $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));

            $paginator = $validator->pendingQuery()
                ->paginate($perPage)
                ->through(fn ($model) => $validator->toEntry($model));

            return ApiResponse::paginated($paginator);
        }

        // Vue d'ensemble : compteur global + aperçu limité par type.
        $queue = [];
        $total = 0;

        foreach ($registry->all() as $key => $validator) {
            $count = $validator->pendingCount();
            $total += $count;

            $queue[$key] = [
                'count' => $count,
                'items' => $validator->pendingQuery()
                    ->limit(self::PREVIEW)
                    ->get()
                    ->map(fn ($model) => $validator->toEntry($model))
                    ->all(),
            ];
        }

        return ApiResponse::success(['queue' => $queue, 'total_pending' => $total]);
    }

    /**
     * Valide ou refuse une ressource. PATCH /api/v1/admin/validate/{type}/{id}
     *
     * Corps : { "decision": "approve"|"reject", "reason"?: string }
     *
     * Autorisation en deux temps : accès back-office (permission de route
     * `consulter:dashboard-admin`) PUIS permission fine propre au type
     * (`valider:bien`, `valider:vehicule`…), vérifiée ici.
     */
    public function decide(ValidateResourceRequest $request, string $type, string $id, ValidatorRegistry $registry): JsonResponse
    {
        $validator = $registry->for($type); // 404 si type inconnu

        // Permission fine : elle dépend du type ciblé.
        if (! $request->user()->can($validator->permission())) {
            abort(403);
        }

        $model = $validator->find($id); // 404 si introuvable

        // Un élément déjà validé ou refusé n'est plus modérable.
        if (! $validator->isPending($model)) {
            throw ValidationException::withMessages([
                'decision' => ['Cet élément a déjà été traité et ne peut plus être modéré.'],
            ]);
        }

        $data = $request->validated();

        $payload = $data['decision'] === 'approve'
            ? $validator->approve($model, $request->user())
            : $validator->reject($model, $request->user(), $data['reason'] ?? null);

        return ApiResponse::success($payload);
    }
}
