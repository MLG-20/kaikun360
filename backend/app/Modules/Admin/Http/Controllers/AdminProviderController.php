<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pro\Enums\ProviderCategory;
use App\Modules\Pro\Enums\ProviderStatus;
use App\Modules\Pro\Http\Resources\ProviderResource;
use App\Modules\Pro\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Supervision des prestataires pour le back-office (F7.2.g — CDC §6
 * « Avis et qualité » : notation prestataire + sanctions).
 *
 * Fournit la liste filtrable des prestataires avec leur note agrégée
 * (`rating_avg`, `rating_count`), leur compteur d'avertissements et le motif de
 * sanction courant. Les actions de sanction elles-mêmes (avertir / suspendre)
 * restent servies par le module Pro (`PATCH /providers/{id}/warn|suspend`,
 * garde `valider:prestataire`) ; cet écran ne fait que les exposer.
 *
 * Réservé à la permission `valider:prestataire`.
 */
class AdminProviderController extends Controller
{
    /**
     * Liste paginée des prestataires. GET /api/v1/admin/providers
     *
     * Filtres : `status` (en_attente / valide / refuse / suspendu),
     * `q` (recherche sur le nom commercial), `category` (F7.2.k).
     *
     * `category` accepte **plusieurs valeurs séparées par une virgule**
     * (`?category=guide,restauration`). C'est ce qui permet à l'écran Tourisme
     * de restituer les **guides** et les **restaurants** du cahier des charges :
     * ils ne sont pas des entités du module Explore mais des **catégories de
     * prestataires** de la marketplace Pro (voir `ProviderCategory`).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', 'string', Rule::in(ProviderStatus::values())],
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        // Découpe et filtre la liste de catégories : une valeur inconnue est
        // ignorée plutôt que de faire échouer l'écran sur un libellé obsolète.
        $categories = collect(explode(',', (string) $request->string('category')))
            ->map(fn (string $c) => trim($c))
            ->filter(fn (string $c) => in_array($c, ProviderCategory::values(), true))
            ->values()
            ->all();

        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));

        $providers = Provider::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            // Un filtre demandé dont AUCUNE valeur n'est valide doit renvoyer
            // une liste vide, jamais le catalogue entier : sans cela une faute
            // de frappe dans l'URL exposerait tous les prestataires.
            ->when($request->filled('category'), fn ($query) => $query->whereIn('category', $categories))
            ->when($request->filled('q'), function ($query) use ($request) {
                $query->where('business_name', 'like', '%'.$request->string('q')->toString().'%');
            })
            ->latest()
            ->paginate($perPage);

        return ProviderResource::collection($providers);
    }
}
