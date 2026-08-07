<?php

namespace App\Modules\Mobility\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Mobility\Enums\MobilityServiceStatus;
use App\Modules\Mobility\Events\MobilityServiceCreated;
use App\Modules\Mobility\Http\Requests\StoreMobilityServiceRequest;
use App\Modules\Mobility\Http\Requests\UpdateMobilityServiceRequest;
use App\Modules\Mobility\Http\Resources\MobilityServiceResource;
use App\Modules\Mobility\Models\MobilityService;
use App\Support\ApiResponse;
use App\Support\Offers\OfferRetirementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Programmation et gestion des DÉPARTS par les prestataires (F8.23).
 *
 * POURQUOI CE CONTRÔLEUR EXISTE
 * -----------------------------
 * ⚠️ La table `mobility_services` était en **lecture seule depuis B7.2**. Le
 * module n'exposait que `GET /mobility-services`, `GET /mobility-services/{id}`
 * et `POST /mobility-services/{id}/bookings` : le catalogue public `/mobilite`
 * ne pouvait donc être alimenté **que par le seeder**, et ni un prestataire ni
 * un agent ne pouvait mettre en vente une navette AIBD, un bus interurbain ou
 * un transfert. Tout l'aval était pourtant branché — fiche publique (F8.10),
 * réservation de places, commission, reversement au partenaire (F8.16.a).
 *
 * Exactement le motif des orphelins de F8.15 : **l'écriture manquait, la
 * lecture et toute la suite attendaient derrière une porte murée.**
 *
 * ⚠️ **Un départ n'est pas un véhicule**, et c'est ce qui justifie un cycle
 * propre plutôt qu'un champ de plus sur `Vehicle` : un même minibus opère
 * Dakar→Saint-Louis le lundi et Dakar→AIBD le mardi. L'offre vendue est le
 * DÉPART daté ; le véhicule n'en est que le moyen (et la source des photos,
 * cf. F8.18).
 */
class MobilityServiceManagementController extends Controller
{
    /**
     * Programme un départ. POST /api/v1/mobility-services
     *
     * Part « en attente de validation », comme un véhicule ou un circuit : la
     * mobilité engage la sécurité de passagers, aucun départ n'atteint le
     * catalogue sans qu'un agent l'ait regardé (CDC §12).
     */
    public function store(StoreMobilityServiceRequest $request): JsonResponse
    {
        $service = MobilityService::create($request->validated() + [
            'reference' => 'TRJ-'.Str::upper(Str::random(8)),
            'provider_id' => $request->user()->id,
            'status' => MobilityServiceStatus::EN_ATTENTE_VALIDATION->value,
        ]);

        MobilityServiceCreated::dispatch($service);

        return ApiResponse::created([
            'mobility_service' => MobilityServiceResource::make($service->load('vehicle.media')),
        ]);
    }

    /**
     * Mes départs (tous statuts). GET /api/v1/mobility-services/mine
     *
     * ⚠️ Route déclarée AVANT `mobility-services/{id}` et hors de la contrainte
     * numérique, sans quoi `mine` serait capté comme un identifiant.
     *
     * Le véhicule et ses photos voyagent avec la liste : c'est depuis elle que
     * le formulaire de correction se préremplit, et le prestataire doit voir
     * quel véhicule il a affecté à quel départ.
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $services = MobilityService::where('provider_id', $request->user()->id)
            ->with('vehicle.media')
            // Le prochain départ d'abord : c'est celui qui se remplit encore et
            // sur lequel le prestataire agit. Les départs passés descendent.
            // ⚠️ `CASE WHEN` et non `departure_at < NOW()` : `NOW()` n'existe
            // pas en SQLite, et la dette de tests lents ([[tests-lents]]) prévoit
            // précisément d'y basculer la suite.
            ->orderByRaw('CASE WHEN departure_at < ? THEN 1 ELSE 0 END', [now()])
            ->orderBy('departure_at')
            ->paginate(15);

        return MobilityServiceResource::collection($services);
    }

    /**
     * Corrige un départ. PATCH /api/v1/mobility-services/{mobility_service}
     *
     * ⚠️ **Le statut n'est pas modifiable ici** : un prestataire ne se publie
     * pas lui-même. Corriger un départ déjà publié ne le renvoie pas non plus
     * en validation — l'agent a validé un trajet et un véhicule, pas un prix ;
     * exiger une nouvelle validation à chaque ajustement tarifaire ferait
     * disparaître l'offre du catalogue pour rien.
     */
    public function update(UpdateMobilityServiceRequest $request, MobilityService $mobilityService): JsonResponse
    {
        Gate::authorize('update', $mobilityService);

        $mobilityService->update($request->validated());

        return ApiResponse::success([
            'mobility_service' => MobilityServiceResource::make(
                $mobilityService->fresh()->load('vehicle.media')
            ),
        ]);
    }

    /**
     * Retire un départ du catalogue. DELETE /api/v1/mobility-services/{mobility_service}
     *
     * Supprime réellement ou retire selon l'histoire du départ : la règle vit
     * dans `OfferRetirementService`, la même que pour les véhicules et les
     * circuits, jamais dupliquée ici. Un départ dont des places ont été vendues
     * est **retiré**, jamais supprimé — sinon les clients qui l'ont réservé
     * verraient leur réservation pointer dans le vide.
     */
    public function destroy(MobilityService $mobilityService, OfferRetirementService $retrait): JsonResponse
    {
        Gate::authorize('update', $mobilityService);

        $resultat = $retrait->retirer($mobilityService);

        return ApiResponse::success([
            'deleted' => $resultat['deleted'],
            'reason' => $resultat['reason'],
        ]);
    }
}
