<?php

namespace App\Modules\Explore\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Explore\Enums\ExperienceStatus;
use App\Modules\Explore\Http\Requests\StoreExperienceRequest;
use App\Modules\Explore\Http\Requests\UpdateExperienceRequest;
use App\Modules\Explore\Http\Resources\ExperienceResource;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Explore\Services\ExperienceDepartureSyncer;
use App\Support\ApiResponse;
use App\Support\Offers\KaikunPublisher;
use App\Support\Offers\OfferRetirementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Publication et suivi des expériences par les PRESTATAIRES (phase B6.2).
 *
 * La publication est réservée aux prestataires vérifiés (policy `create`) — le
 * `super_admin` y accède aussi via `Gate::before` (F21) : le back-office peut
 * ainsi déposer un circuit lui-même quand aucun prestataire n'en a encore
 * proposé, au lieu de rester en pure supervision. Une expérience déposée par
 * un prestataire part « en attente de validation », comme avant ; un circuit
 * déposé par le `super_admin` depuis le back-office est publié D'EMBLÉE — il
 * n'y a personne d'autre à qui le faire valider (F21, retour client).
 */
class ExperienceManagementController extends Controller
{
    /**
     * Publie une expérience (en attente de validation, sauf super_admin — voir
     * plus haut). POST /api/v1/experiences
     */
    public function store(StoreExperienceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $departures = $data['departures'];
        unset($data['departures']);

        $publication = KaikunPublisher::isKaikunUser($request->user())
            ? KaikunPublisher::directPublication($request->user(), ExperienceStatus::PUBLIE->value)
            : ['status' => ExperienceStatus::EN_ATTENTE_VALIDATION->value];

        $experience = TourismExperience::create($data + $publication + [
            'reference' => 'EXP-'.Str::upper(Str::random(8)),
            'provider_id' => $request->user()->id,
        ]);

        $experience->departures()->createMany($departures);

        return ApiResponse::created([
            'experience' => ExperienceResource::make($experience->load('departures')),
        ]);
    }

    /**
     * Mes expériences (tous statuts). GET /api/v1/experiences/mine
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        // F8.18 — idem véhicules : le formulaire d'édition lit cette liste.
        $experiences = TourismExperience::where('provider_id', $request->user()->id)
            ->with(['media', 'departures'])
            ->latest()
            ->paginate(15);

        return ExperienceResource::collection($experiences);
    }

    /**
     * Met à jour un circuit. PATCH /api/v1/experiences/{experience}
     *
     * ⚠️ **Cette route n'existait pas** (F8.19), et c'était le trou le plus
     * visible du module : un circuit déposé était **définitif**. Une faute de
     * frappe dans le titre, un prix qui change, un départ qui se décale — rien
     * n'était modifiable, et surtout **aucune photo ne pouvait être ajoutée
     * après coup**, l'illustration n'étant possible qu'au moment du dépôt.
     *
     * Le statut n'est pas modifiable ici : il évolue par la validation d'un
     * agent, comme pour les véhicules et les biens.
     */
    public function update(
        UpdateExperienceRequest $request,
        TourismExperience $experience,
        ExperienceDepartureSyncer $departureSyncer,
    ): JsonResponse {
        Gate::authorize('update', $experience);

        $data = $request->validated();
        $departures = $data['departures'] ?? null;
        unset($data['departures']);

        $experience->update($data);

        if ($departures !== null) {
            $departureSyncer->sync($experience, $departures);
        }

        return ApiResponse::success([
            'experience' => ExperienceResource::make($experience->fresh()->load(['media', 'departures'])),
        ]);
    }

    /**
     * Retire un circuit du catalogue. DELETE /api/v1/experiences/{experience}
     *
     * ⚠️ **Supprime réellement, ou retire, selon l'histoire de l'offre** — la
     * règle vit dans `OfferRetirementService` et nulle part ailleurs. Un circuit
     * déjà réservé n'est jamais supprimé : les réservations le désignent par une
     * relation polymorphe, sans clé étrangère pour les retenir.
     */
    public function destroy(TourismExperience $experience, OfferRetirementService $retrait): JsonResponse
    {
        Gate::authorize('update', $experience);

        $resultat = $retrait->retirer($experience);

        return ApiResponse::success([
            'deleted' => $resultat['deleted'],
            // L'écran doit pouvoir DIRE au prestataire ce qui vient de se passer :
            // « supprimé » et « retiré mais conservé » ne sont pas la même chose.
            'reason' => $resultat['reason'],
        ]);
    }
}
