<?php

namespace App\Modules\Stay\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Immo\Models\Property;
use App\Modules\Stay\Http\Requests\UpsertStayRequest;
use App\Modules\Stay\Http\Resources\StayResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion de la config « nuitées » d'un bien par son propriétaire (F4.3).
 *
 * Un bien peut être loué au mois (`price_xof` sur le bien), à la nuitée (config
 * `Stay` 1–1 gérée ici) ou les deux (« mixte »). Ces endpoints permettent au
 * propriétaire d'activer/paramétrer ou de retirer le mode nuitées de son bien.
 *
 * L'isolation est garantie par la PropertyPolicy : seul le propriétaire du bien
 * (ou un admin) peut agir dessus.
 */
class StayManagementController extends Controller
{
    /**
     * Crée ou met à jour la config nuitées du bien.
     * PUT /api/v1/properties/{property}/stay
     *
     * Idempotent (upsert 1–1) : réenregistrer réactive une config qui aurait été
     * désactivée. L'autorisation est portée par UpsertStayRequest.
     */
    public function upsert(UpsertStayRequest $request, Property $property): JsonResponse
    {
        $existed = $property->stay()->exists();

        $stay = $property->stay()->updateOrCreate(
            ['property_id' => $property->id],
            [...$request->validated(), 'is_active' => true],
        );

        activity()->causedBy($request->user())->performedOn($stay)
            ->log($existed ? 'Modification de la config nuitées' : 'Activation du mode nuitées');

        $resource = StayResource::make($stay->refresh());

        // 201 à la création, 200 à la mise à jour.
        return $existed
            ? ApiResponse::success(['stay' => $resource])
            : ApiResponse::created(['stay' => $resource]);
    }

    /**
     * Retire le mode nuitées du bien.
     * DELETE /api/v1/properties/{property}/stay
     *
     * Si des réservations existent, on ne supprime pas (intégrité de
     * l'historique) : on désactive la config (`is_active = false`) → le bien
     * disparaît du catalogue nuitées mais les réservations passées restent
     * cohérentes. Sinon, on supprime la config.
     */
    public function destroy(Request $request, Property $property): JsonResponse
    {
        abort_unless($request->user()?->can('update', $property) ?? false, 403);

        $stay = $property->stay;
        abort_unless($stay !== null, 404);

        if ($stay->bookings()->exists()) {
            $stay->update(['is_active' => false]);
            activity()->causedBy($request->user())->performedOn($stay)
                ->log('Désactivation du mode nuitées');

            return ApiResponse::success([
                'message' => 'Mode nuitées désactivé (des réservations existent, la config est conservée).',
            ]);
        }

        activity()->causedBy($request->user())->performedOn($property)->log('Retrait du mode nuitées');
        $stay->delete();

        return ApiResponse::success(['message' => 'Mode nuitées retiré.']);
    }
}
