<?php

namespace App\Modules\TeamBuilding\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Modules\TeamBuilding\Enums\TeamBuildingQuoteStatus;
use App\Modules\TeamBuilding\Enums\TeamBuildingRequestStatus;
use App\Modules\TeamBuilding\Events\QuoteAccepted;
use App\Modules\TeamBuilding\Events\QuoteSent;
use App\Modules\TeamBuilding\Http\Requests\ComposeQuoteRequest;
use App\Modules\TeamBuilding\Http\Resources\TeamBuildingQuoteResource;
use App\Modules\TeamBuilding\Models\TeamBuildingQuote;
use App\Modules\TeamBuilding\Models\TeamBuildingRequest;
use App\Modules\TeamBuilding\Notifications\TeamBuildingQuoteAcceptedNotification;
use App\Modules\TeamBuilding\Services\TeamBuildingQuoteComposer;
use App\Services\QuoteConversionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Devis de team building : composition & envoi (back-office), acceptation
 * (entreprise), consultation (phase B9.3).
 */
class TeamBuildingQuoteController extends Controller
{
    /**
     * Liste des devis d'une demande. GET /api/v1/team-building-requests/{request}/quotes
     */
    public function index(TeamBuildingRequest $teamBuildingRequest): AnonymousResourceCollection
    {
        Gate::authorize('view', $teamBuildingRequest);

        return TeamBuildingQuoteResource::collection($teamBuildingRequest->quotes()->latest()->get());
    }

    /**
     * Compose un devis (back-office). POST /api/v1/team-building-requests/{request}/quotes
     */
    public function compose(
        ComposeQuoteRequest $request,
        TeamBuildingRequest $teamBuildingRequest,
        TeamBuildingQuoteComposer $composer
    ): JsonResponse {
        Gate::authorize('manage', $teamBuildingRequest);

        $data = $request->validated();
        $quote = $composer->composeFor(
            $teamBuildingRequest,
            $data['components'],
            $data['margin_rate'] ?? null
        );

        // La demande passe « en étude » dès qu'un devis est composé.
        $teamBuildingRequest->update(['status' => TeamBuildingRequestStatus::EN_ETUDE->value]);

        return ApiResponse::created(['quote' => TeamBuildingQuoteResource::make($quote)]);
    }

    /**
     * Envoie un devis à l'entreprise (back-office). PATCH /api/v1/team-building-quotes/{quote}/send
     */
    public function send(TeamBuildingQuote $quote): JsonResponse
    {
        Gate::authorize('manage', $quote->request);

        $quote->update([
            'status' => TeamBuildingQuoteStatus::ENVOYE->value,
            'sent_at' => now(),
        ]);
        $quote->request->update(['status' => TeamBuildingRequestStatus::DEVIS_ENVOYE->value]);

        QuoteSent::dispatch($quote);

        return ApiResponse::success(['quote' => TeamBuildingQuoteResource::make($quote->fresh())]);
    }

    /**
     * Accepte un devis (entreprise). PATCH /api/v1/team-building-quotes/{quote}/accept
     */
    public function accept(TeamBuildingQuote $quote, QuoteConversionService $conversion): JsonResponse
    {
        Gate::authorize('accept', $quote->request);

        // Un devis n'est acceptable que s'il a été envoyé.
        if ($quote->status !== TeamBuildingQuoteStatus::ENVOYE) {
            throw ValidationException::withMessages([
                'status' => ['Seul un devis envoyé peut être accepté.'],
            ]);
        }

        $quote->update([
            'status' => TeamBuildingQuoteStatus::ACCEPTE->value,
            'accepted_at' => now(),
        ]);
        $quote->request->update(['status' => TeamBuildingRequestStatus::ACCEPTE->value]);

        // F8.14 — L'ACCEPTATION DEVIENT EXIGIBLE.
        //
        // ⚠️ Jusqu'ici, cette méthode ne faisait que changer deux colonnes
        // `status` : aucune réservation n'était créée, donc aucun montant n'était
        // payable (`POST /payments/initiate` exige un `booking_id`). Une
        // entreprise pouvait demander un séminaire, recevoir un devis,
        // l'accepter — et le circuit s'arrêtait là, sans que rien ne le lui dise.
        // C'est exactement le trou que F8.11 avait bouché sur les devis
        // génériques ; ce module, qui a ses propres devis, avait été oublié.
        //
        // La conversion est idempotente : un double clic ne crée pas deux
        // montants à payer pour un seul séminaire.
        $booking = $conversion->convertTeamBuilding($quote);

        // Déclenche le suivi opérationnel multi-prestataires.
        QuoteAccepted::dispatch($quote);

        // Notifiée APRÈS la conversion (elle tourne en transaction) : un e-mail
        // parti avant un rollback annoncerait une réservation inexistante.
        $quote->request->company?->notify(
            new TeamBuildingQuoteAcceptedNotification($quote, $booking)
        );

        return ApiResponse::success([
            // `load('booking')` : le devis renvoyé porte lui aussi sa réservation,
            // pour que l'écran n'ait pas deux façons de la lire selon qu'il vient
            // de l'acceptation ou d'un rechargement.
            'quote' => TeamBuildingQuoteResource::make($quote->fresh()->load('booking')),
            // C'est elle qui porte le montant exigible et l'identifiant attendu
            // par l'écran de règlement de l'espace entreprise.
            'booking' => BookingResource::make($booking),
        ]);
    }
}
