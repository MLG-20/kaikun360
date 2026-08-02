<?php

namespace App\Http\Controllers;

use App\Enums\QuoteStatus;
use App\Http\Requests\RespondQuoteRequest;
use App\Http\Requests\StoreQuoteRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use App\Models\ServiceRequest;
use App\Notifications\QuoteAnsweredNotification;
use App\Notifications\QuoteReceivedNotification;
use App\Services\QuoteConversionService;
use App\Support\ApiResponse;
use App\Support\Webhooks\WebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Devis génériques — couche transversale (phase B11.3).
 */
class QuoteController extends Controller
{
    /**
     * Propose un devis pour une demande (agents/admin).
     * POST /api/v1/requests/{request}/quotes
     */
    public function store(StoreQuoteRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $quote = $serviceRequest->quotes()->create($request->validated() + [
            'reference' => 'QTE-'.Str::upper(Str::random(8)),
            // F8.11 — on gardait le devis sans son auteur. Le client recevait
            // donc un montant tombé d'une plateforme anonyme, et personne
            // n'était prévenu quand il l'acceptait. L'agent qui chiffre devient
            // l'interlocuteur nommé du dossier.
            'agent_id' => $request->user()->id,
            'status' => QuoteStatus::ENVOYE->value,
        ]);

        // Informe le demandeur qu'un devis lui est proposé (async, B16.2).
        $serviceRequest->user?->notify(new QuoteReceivedNotification($quote));

        // Émet l'événement vers n8n (automatisation WhatsApp…) — B18.1.
        WebhookDispatcher::emit(WebhookDispatcher::QUOTE_RECEIVED, [
            'quote_reference' => $quote->reference,
            'request_reference' => $serviceRequest->reference,
            'amount_xof' => $quote->amount_xof,
            'user' => [
                'name' => $serviceRequest->user?->name,
                'phone' => $serviceRequest->user?->phone,
            ],
        ]);

        return ApiResponse::created(['quote' => QuoteResource::make($quote)]);
    }

    /**
     * Consulte un devis. GET /api/v1/quotes/{quote}
     */
    public function show(Quote $quote): JsonResponse
    {
        Gate::authorize('view', $quote);

        // `agent` et `booking` sont chargés ici parce que l'écran du devis les
        // affiche systématiquement : l'interlocuteur nommé en tête (F8.11), et
        // la réservation à régler si le devis est déjà accepté — sans quoi un
        // client revenant sur la page perdrait le chemin vers son paiement.
        return ApiResponse::success([
            'quote' => QuoteResource::make($quote->load(['agent', 'booking'])),
        ]);
    }

    /**
     * Accepte/refuse un devis (demandeur). PATCH /api/v1/quotes/{quote}
     *
     * ⚠️ **Accepter ne se contentait que de changer une colonne** (F8.11). Le
     * client disait « oui » et rien ne devenait exigible : aucune réservation,
     * donc aucun paiement possible (`POST /payments/initiate` réclame un
     * `booking_id`). Le circuit du sur-mesure s'arrêtait sur un accord sans
     * suite. L'acceptation crée désormais la réservation payable.
     *
     * Le règlement est **proposé, jamais imposé** : la réponse porte la
     * réservation créée, l'écran invite à régler, mais rien ne redirige de force
     * vers PayTech — sur du sur-mesure, le client doit pouvoir demander à être
     * rappelé plutôt que d'être poussé vers un formulaire de carte.
     */
    public function respond(
        RespondQuoteRequest $request,
        Quote $quote,
        QuoteConversionService $conversion,
    ): JsonResponse {
        Gate::authorize('respond', $quote);

        // On ne répond qu'à un devis envoyé (ni brouillon, ni déjà tranché).
        if ($quote->status !== QuoteStatus::ENVOYE) {
            throw ValidationException::withMessages([
                'status' => ['Seul un devis envoyé peut être accepté ou refusé.'],
            ]);
        }

        $decision = QuoteStatus::from($request->validated()['status']);
        $quote->update(['status' => $decision->value]);

        // Un refus ne produit rien d'exigible : on prévient l'agent, et c'est tout.
        $booking = $decision === QuoteStatus::ACCEPTE
            ? $conversion->convert($quote)
            : null;

        // L'agent qui a chiffré est prévenu de la décision — lui seul, pas toute
        // l'équipe : c'est SON dossier. Notifié APRÈS la conversion, pour ne
        // jamais annoncer une réservation qu'un rollback aurait effacée.
        $quote->agent?->notify(new QuoteAnsweredNotification($quote, $booking));

        return ApiResponse::success([
            'quote' => QuoteResource::make($quote->fresh()->load(['agent', 'booking'])),
            // Présente uniquement en cas d'acceptation : c'est elle qui porte le
            // montant à régler et l'identifiant attendu par l'écran de paiement.
            'booking' => $booking ? BookingResource::make($booking) : null,
        ]);
    }
}
