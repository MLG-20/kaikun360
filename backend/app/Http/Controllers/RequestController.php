<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Events\RequestCreated;
use App\Events\RequestStatusChanged;
use App\Http\Requests\StoreRequestRequest;
use App\Http\Requests\UpdateRequestStatusRequest;
use App\Http\Resources\ServiceRequestResource;
use App\Models\ServiceRequest;
use App\Support\ApiResponse;
use App\Support\Trash\PersonalHiding;
use App\Support\Webhooks\WebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Demandes clients génériques — couche transversale (phase B11.2).
 */
class RequestController extends Controller
{
    /**
     * Crée une demande. POST /api/v1/requests
     */
    public function store(StoreRequestRequest $request): JsonResponse
    {
        $serviceRequest = ServiceRequest::create($request->validated() + [
            'reference' => 'REQ-'.Str::upper(Str::random(8)),
            'user_id' => $request->user()->id,
            'status' => RequestStatus::RECU->value,
        ]);

        // Prévient les agents disponibles.
        RequestCreated::dispatch($serviceRequest);

        return ApiResponse::created(['request' => ServiceRequestResource::make($serviceRequest)]);
    }

    /**
     * Mes demandes. GET /api/v1/requests/my
     */
    public function my(Request $request): AnonymousResourceCollection
    {
        $requests = ServiceRequest::where('user_id', $request->user()->id)
            // F11.5 — les demandes rangées par le client quittent SA liste.
            // ⚠️ Ce filtre n'existe qu'ici et dans `BookingController::my` : la
            // ligne reste entière en base et le back-office continue de la voir,
            // parce qu'une demande clôturée reste une pièce du dossier de Kaikun.
            ->whereNull('hidden_at')
            ->latest()
            ->paginate(15);

        return ServiceRequestResource::collection($requests);
    }

    /**
     * Range une de MES demandes dans ma corbeille. POST /api/v1/requests/{request}/hide
     *
     * ⚠️ **Ne supprime rien** (voir la migration `add_hidden_at_…`) : la demande
     * disparaît de la liste du client et de nulle part ailleurs. C'est pour
     * cela que le geste vit ici, dans le contrôleur qui possède déjà la règle
     * « cette demande est-elle à moi ? », et non dans `TrashController` — qui,
     * lui, ne sait que regarder et restaurer.
     */
    public function hide(Request $request, ServiceRequest $serviceRequest, PersonalHiding $corbeille): JsonResponse
    {
        abort_unless($serviceRequest->user_id === $request->user()->id, 403);

        // ⚠️ On ne range que ce qui est TERMINÉ : masquer une demande qu'un
        // agent traite encore fabriquerait un oubli, pas un allègement.
        if ($raison = $corbeille->raisonDeBlocage($serviceRequest, $request->user())) {
            return ApiResponse::error($raison, 422);
        }

        $corbeille->masquer($serviceRequest, $request->user());

        return ApiResponse::success([
            'message' => 'Demande rangée dans votre corbeille. Elle y reste disponible : rien n’est supprimé.',
        ]);
    }

    /**
     * Détail d'une de MES demandes. GET /api/v1/requests/{request}
     *
     * Réservé au propriétaire de la demande (le demandeur) : une demande qui
     * n'appartient pas à l'utilisateur connecté renvoie 403. Alimente l'écran
     * « détail d'une demande » de l'espace client (F3.3).
     */
    public function show(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        abort_unless($serviceRequest->user_id === $request->user()->id, 403);

        return ApiResponse::success(['request' => ServiceRequestResource::make($serviceRequest)]);
    }

    /**
     * Change le statut (agents/admin). PATCH /api/v1/requests/{request}/status
     *
     * Applique la machine à états STRICTE : toute transition invalide est
     * rejetée (422).
     */
    public function updateStatus(UpdateRequestStatusRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $from = $serviceRequest->status;
        $to = RequestStatus::from($request->validated()['status']);

        if (! $from->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => ["Transition invalide : « {$from->value} » → « {$to->value} »."],
            ]);
        }

        $serviceRequest->update(['status' => $to->value]);

        activity()->causedBy($request->user())->performedOn($serviceRequest)
            ->withProperties(['from' => $from->value, 'to' => $to->value])
            ->log('Changement de statut de demande');

        RequestStatusChanged::dispatch($serviceRequest, $from, $to);

        // Émet l'événement vers n8n (automatisation WhatsApp…) — B18.1.
        WebhookDispatcher::emit(WebhookDispatcher::REQUEST_STATUS_CHANGED, [
            'request_reference' => $serviceRequest->reference,
            'from' => $from->value,
            'to' => $to->value,
            'user' => [
                'name' => $serviceRequest->user?->name,
                'phone' => $serviceRequest->user?->phone,
            ],
        ]);

        return ApiResponse::success(['request' => ServiceRequestResource::make($serviceRequest->fresh())]);
    }
}
