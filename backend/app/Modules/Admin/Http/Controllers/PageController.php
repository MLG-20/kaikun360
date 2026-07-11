<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Modules\Admin\Http\Requests\StorePageRequest;
use App\Modules\Admin\Http\Requests\UpdatePageRequest;
use App\Modules\Admin\Http\Resources\PageResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Gestion des pages de contenu (B13.4). Lecture publique par slug (pages
 * publiées uniquement) ; édition réservée à `gerer:parametres`.
 */
class PageController extends Controller
{
    /**
     * Affichage public d'une page publiée. GET /api/v1/pages/{page}
     * (résolue par slug ; 404 si absente ou non publiée).
     */
    public function show(Page $page): JsonResponse
    {
        abort_unless($page->is_published, 404);

        return ApiResponse::success(['page' => PageResource::make($page)]);
    }

    /**
     * Liste complète pour le back-office. GET /api/v1/admin/pages
     */
    public function index(): AnonymousResourceCollection
    {
        return PageResource::collection(Page::orderBy('slug')->get());
    }

    /**
     * Crée une page. POST /api/v1/admin/pages
     */
    public function store(StorePageRequest $request): JsonResponse
    {
        $page = Page::create($request->validated() + ['updated_by' => $request->user()->id]);

        return ApiResponse::created(['page' => PageResource::make($page)]);
    }

    /**
     * Met à jour une page. PATCH /api/v1/admin/pages/{page}
     */
    public function update(UpdatePageRequest $request, Page $page): JsonResponse
    {
        $page->update($request->validated() + ['updated_by' => $request->user()->id]);

        return ApiResponse::success(['page' => PageResource::make($page->fresh())]);
    }

    /**
     * Supprime une page. DELETE /api/v1/admin/pages/{page}
     */
    public function destroy(Page $page): JsonResponse
    {
        $page->delete();

        return ApiResponse::noContent();
    }
}
