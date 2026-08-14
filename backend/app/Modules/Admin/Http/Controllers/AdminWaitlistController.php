<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Enums\WaitlistCategory;
use App\Enums\WaitlistEntryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWaitlistEntryRequest;
use App\Http\Resources\WaitlistEntryResource;
use App\Models\WaitlistEntry;
use App\Notifications\WaitlistEntryProcessedNotification;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Notification;

/**
 * Écran de consultation de la liste d'attente pour l'équipe (2026-08-14).
 *
 * Jusqu'ici, une inscription (`WaitlistEntry`) n'était visible nulle part au
 * back-office — la seule trace était l'e-mail d'alerte
 * (`NewWaitlistEntryNotification`). Même patron que `ContactController` :
 * lecture filtrable par catégorie/statut, changement de statut qui
 * enregistre l'agent et l'horodatage.
 */
class AdminWaitlistController extends Controller
{
    /**
     * Liste des inscriptions. GET /api/v1/admin/waitlist
     *
     * Filtrable par `category` et `status`, du plus récent au plus ancien.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $entries = WaitlistEntry::query()
            ->with('handledBy:id,name')
            ->when(
                $request->filled('category'),
                fn ($query) => $query->where('category', $request->string('category')->toString()),
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->toString()),
            )
            ->latest()
            ->paginate(20);

        // Le compteur des inscriptions NON TRAITÉES voyage avec la page : filtrer
        // sur « traité » ne doit pas faire disparaître le nombre de celles qui
        // attendent encore, même logique que ContactController::index.
        return WaitlistEntryResource::collection($entries)->additional([
            'meta' => [
                'pending' => WaitlistEntry::where('status', WaitlistEntryStatus::NOUVEAU->value)->count(),
            ],
        ]);
    }

    /**
     * Fiche d'une inscription. GET /api/v1/admin/waitlist/{waitlistEntry}
     */
    public function show(WaitlistEntry $waitlistEntry): JsonResponse
    {
        return ApiResponse::success([
            'waitlist_entry' => WaitlistEntryResource::make(
                $waitlistEntry->load('handledBy:id,name'),
            ),
        ]);
    }

    /**
     * Change le statut d'une inscription. PATCH /api/v1/admin/waitlist/{waitlistEntry}
     *
     * Le passage à « traité » enregistre l'agent et l'horodatage ; le retour à
     * « nouveau » les efface.
     *
     * ⚠️ **Le prospect est invité à rejoindre la plateforme au moment précis où
     * il BASCULE vers « traité »** — pas à chaque `PATCH` (un agent qui rouvre
     * puis referme le dossier ne doit pas renvoyer l'e-mail), et seulement s'il
     * a laissé une adresse (`email` est facultatif sur le formulaire public).
     */
    public function update(UpdateWaitlistEntryRequest $request, WaitlistEntry $waitlistEntry): JsonResponse
    {
        $status = WaitlistEntryStatus::from($request->validated()['status']);
        $traite = $status === WaitlistEntryStatus::TRAITE;
        $bascule = $traite && $waitlistEntry->status !== WaitlistEntryStatus::TRAITE;

        $waitlistEntry->update([
            'status' => $status->value,
            'handled_by' => $traite ? $request->user()->id : null,
            'handled_at' => $traite ? now() : null,
        ]);

        activity()->causedBy($request->user())->performedOn($waitlistEntry)
            ->log("Traitement d'inscription à la liste d'attente");

        if ($bascule && $waitlistEntry->email) {
            Notification::route('mail', $waitlistEntry->email)
                ->notify(new WaitlistEntryProcessedNotification($waitlistEntry));
        }

        return ApiResponse::success([
            'waitlist_entry' => WaitlistEntryResource::make($waitlistEntry->fresh()->load('handledBy:id,name')),
        ]);
    }

    /**
     * Référentiels de filtrage. GET /api/v1/admin/waitlist/filters
     */
    public function filters(): JsonResponse
    {
        $lister = fn (array $cases) => collect($cases)
            ->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()])
            ->all();

        return ApiResponse::success([
            'categories' => $lister(WaitlistCategory::cases()),
            'statuses' => $lister(WaitlistEntryStatus::cases()),
        ]);
    }
}
