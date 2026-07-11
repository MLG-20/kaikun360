<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Modules\Admin\Http\Requests\StoreFaqRequest;
use App\Modules\Admin\Http\Requests\UpdateFaqRequest;
use App\Modules\Admin\Http\Resources\FaqResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Gestion de la FAQ (B13.4). La lecture publique est ouverte ; l'édition est
 * réservée à la permission `gerer:parametres` (appliquée sur les routes admin).
 */
class FaqController extends Controller
{
    /**
     * Liste publique des entrées publiées. GET /api/v1/faqs
     */
    public function published(): AnonymousResourceCollection
    {
        return FaqResource::collection(Faq::published()->get());
    }

    /**
     * Liste complète (publiées + masquées) pour le back-office.
     * GET /api/v1/admin/faqs
     */
    public function index(): AnonymousResourceCollection
    {
        return FaqResource::collection(Faq::orderBy('position')->orderBy('id')->get());
    }

    /**
     * Crée une entrée. POST /api/v1/admin/faqs
     */
    public function store(StoreFaqRequest $request): JsonResponse
    {
        $faq = Faq::create($request->validated() + ['updated_by' => $request->user()->id]);

        return ApiResponse::created(['faq' => FaqResource::make($faq)]);
    }

    /**
     * Met à jour une entrée. PATCH /api/v1/admin/faqs/{faq}
     */
    public function update(UpdateFaqRequest $request, Faq $faq): JsonResponse
    {
        $faq->update($request->validated() + ['updated_by' => $request->user()->id]);

        return ApiResponse::success(['faq' => FaqResource::make($faq->fresh())]);
    }

    /**
     * Supprime une entrée. DELETE /api/v1/admin/faqs/{faq}
     */
    public function destroy(Faq $faq): JsonResponse
    {
        $faq->delete();

        return ApiResponse::noContent();
    }
}
