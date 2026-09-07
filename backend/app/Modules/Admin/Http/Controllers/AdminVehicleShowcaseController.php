<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\VehicleShowcaseCard;
use App\Modules\Admin\Http\Requests\StoreVehicleShowcaseCardRequest;
use App\Modules\Admin\Http\Requests\UpdateVehicleShowcaseCardRequest;
use App\Modules\Admin\Http\Resources\VehicleShowcaseCardResource;
use App\Services\ImageProcessor;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

/**
 * Pilotage de la section « Location de véhicules » de l'accueil, qui
 * remplace l'ancienne section « Protocole de confiance » (demande client,
 * 2026-09-07). Même permission que les actualités et les bandeaux : du
 * contenu de vitrine, pas un dossier client.
 */
class AdminVehicleShowcaseController extends Controller
{
    /**
     * Toutes les cartes, publiées ou non. GET /api/v1/admin/vehicle-showcase-cards
     */
    public function index(): AnonymousResourceCollection
    {
        return VehicleShowcaseCardResource::collection(VehicleShowcaseCard::ordered()->get());
    }

    /**
     * Crée une carte. POST /api/v1/admin/vehicle-showcase-cards
     */
    public function store(StoreVehicleShowcaseCardRequest $request, ImageProcessor $images): JsonResponse
    {
        $data = $request->safe()->only(['title', 'description', 'category', 'link_url', 'link_label']);

        $stored = $images->storeCompressed(
            $request->file('image'),
            'vehicle-showcase',
            ImageProcessor::MAX_WIDTH,
            ImageProcessor::JPEG_QUALITY,
        );
        $data['image_path'] = $stored['path'];

        $card = VehicleShowcaseCard::create($data + [
            'is_published' => (bool) $request->boolean('is_published'),
            'position' => $request->input('position', 0),
            'updated_by' => $request->user()->id,
        ]);

        activity()->causedBy($request->user())->performedOn($card)
            ->log("Création de la carte véhicule « {$card->title} »");

        return ApiResponse::created(['card' => VehicleShowcaseCardResource::make($card)]);
    }

    /**
     * Met à jour une carte. POST /api/v1/admin/vehicle-showcase-cards/{card}
     *
     * En POST et non PATCH : multipart ne se décode que sur POST.
     */
    public function update(UpdateVehicleShowcaseCardRequest $request, VehicleShowcaseCard $card, ImageProcessor $images): JsonResponse
    {
        $data = $request->safe()->except(['image']);

        foreach (['title', 'description', 'category', 'link_url', 'link_label'] as $field) {
            if (array_key_exists($field, $data)) {
                $card->{$field} = $data[$field];
            }
        }

        if ($request->hasFile('image')) {
            if ($card->image_path) {
                Storage::disk('public')->delete($card->image_path);
            }
            $stored = $images->storeCompressed(
                $request->file('image'),
                'vehicle-showcase',
                ImageProcessor::MAX_WIDTH,
                ImageProcessor::JPEG_QUALITY,
            );
            $card->image_path = $stored['path'];
        }

        if ($request->has('is_published')) {
            $card->is_published = $request->boolean('is_published');
        }

        if ($request->has('position')) {
            $card->position = (int) $request->input('position');
        }

        $card->updated_by = $request->user()->id;
        $card->save();

        activity()->causedBy($request->user())->performedOn($card)
            ->log("Mise à jour de la carte véhicule « {$card->title} »");

        return ApiResponse::success(['card' => VehicleShowcaseCardResource::make($card->fresh())]);
    }

    /**
     * Supprime une carte (et son image). DELETE /api/v1/admin/vehicle-showcase-cards/{card}
     */
    public function destroy(VehicleShowcaseCard $card): JsonResponse
    {
        if ($card->image_path) {
            Storage::disk('public')->delete($card->image_path);
        }

        activity()->causedBy(request()->user())->performedOn($card)
            ->log("Suppression de la carte véhicule « {$card->title} »");

        $card->delete();

        return ApiResponse::noContent();
    }
}
