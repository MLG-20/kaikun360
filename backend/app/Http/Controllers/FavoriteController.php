<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFavoriteRequest;
use App\Http\Resources\FavoriteResource;
use App\Models\Favorite;
use App\Support\ApiResponse;
use App\Support\Favoritables;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Favoris de l'utilisateur — couche transversale, POLYMORPHE (tous univers).
 *
 * Généralise les favoris (autrefois limités à l'immobilier) à tous les univers
 * favorisables : bien, nuitée, véhicule, expérience, service de mobilité (voir le
 * registre `App\Support\Favoritables`). Accès toujours scopé à l'utilisateur
 * courant (`$request->user()->favorites()`).
 */
class FavoriteController extends Controller
{
    /**
     * Liste paginée de mes favoris, tous univers confondus, les plus récents
     * d'abord. GET /api/v1/favorites
     *
     * Chaque favori est rendu avec l'élément favorisé projeté par la ressource de
     * son univers (même forme que le catalogue). L'élément polymorphe est chargé
     * en une passe (`morphWith` par type) pour éviter les requêtes N+1.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $favorites = $request->user()
            ->favorites()
            ->with(['favoritable' => fn (MorphTo $morphTo) => Favoritables::withRelations($morphTo)])
            ->latest()
            ->paginate(15);

        return FavoriteResource::collection($favorites);
    }

    /**
     * Identifiants de mes favoris, regroupés par type. GET /api/v1/favorites/ids
     *
     * Charge utile légère (pas de données d'affichage) permettant au catalogue de
     * marquer d'un cœur plein les éléments déjà favorisés, sans requête par carte.
     * Forme : { property: [1, 2], stay: [7], vehicle: [], ... }.
     */
    public function ids(Request $request): JsonResponse
    {
        // On initialise toutes les clés (même vides) pour un contrat stable.
        $grouped = array_fill_keys(Favoritables::slugs(), []);

        $favorites = $request->user()
            ->favorites()
            ->get(['favoritable_type', 'favoritable_id']);

        foreach ($favorites as $favorite) {
            $slug = Favoritables::slugForClass($favorite->favoritable_type);
            if ($slug !== null) {
                $grouped[$slug][] = $favorite->favoritable_id;
            }
        }

        return ApiResponse::success($grouped);
    }

    /**
     * Ajoute un élément aux favoris. POST /api/v1/favorites  { type, id }
     *
     * On ne peut favoriser qu'un élément EXISTANT et VISIBLE (publié / réservable) :
     * sinon 404. Idempotent (`firstOrCreate`) : favoriser deux fois ne crée pas de
     * doublon.
     */
    public function store(StoreFavoriteRequest $request): JsonResponse
    {
        $type = $request->validated('type');
        $id = (int) $request->validated('id');

        $item = Favoritables::findVisible($type, $id);
        abort_if($item === null, 404);

        $request->user()->favorites()->firstOrCreate([
            'favoritable_type' => $item::class,
            'favoritable_id' => $item->getKey(),
        ]);

        return ApiResponse::success(['message' => 'Ajouté à vos favoris.']);
    }

    /**
     * Retire un élément des favoris. DELETE /api/v1/favorites/{type}/{id}
     *
     * Scopé à l'utilisateur : ne retire que SON favori (aucun effet sur autrui).
     */
    public function destroy(Request $request, string $type, int $id): JsonResponse
    {
        $class = Favoritables::modelClass($type);
        abort_if($class === null, 404);

        $request->user()->favorites()
            ->where('favoritable_type', $class)
            ->where('favoritable_id', $id)
            ->delete();

        return ApiResponse::success(['message' => 'Retiré de vos favoris.']);
    }
}
