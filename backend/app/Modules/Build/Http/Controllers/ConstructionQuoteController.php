<?php

namespace App\Modules\Build\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Modules\Build\Enums\ConstructionQuoteStatus;
use App\Modules\Build\Enums\ConstructionRequestStatus;
use App\Modules\Build\Events\ConstructionQuoteSent;
use App\Modules\Build\Http\Requests\ComposeConstructionQuoteRequest;
use App\Modules\Build\Http\Resources\ConstructionQuoteResource;
use App\Modules\Build\Models\ConstructionQuote;
use App\Modules\Build\Models\ConstructionRequest;
use App\Modules\Build\Notifications\ConstructionQuoteAcceptedNotification;
use App\Modules\Build\Services\ConstructionQuoteComposer;
use App\Services\QuoteConversionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Devis de chantier : composition & envoi (back-office), réponse (client) — F7.3.e2.
 *
 * Comble une fonction du CDC §6 *Construction* (« demandes de devis ») qui n'avait
 * AUCUNE implémentation : la plateforme ne produisait qu'un coût estimé par le
 * simulateur, pas un devis ventilé par lot, envoyé puis accepté ou refusé.
 *
 * Le cycle alimente enfin les statuts que l'enum `ConstructionRequestStatus`
 * prévoyait depuis B5 sans que rien ne les pilote :
 *   composition → EN_ETUDE · envoi → DEVIS_ENVOYE · acceptation → ACCEPTEE.
 *
 * Découpage des droits : chiffrer et envoyer relèvent de `gerer:chantiers`
 * (middleware `can:` sur les routes) ; **répondre** relève de la policy `respond`,
 * réservée au client — accepter un devis est son engagement, pas celui de l'agent.
 */
class ConstructionQuoteController extends Controller
{
    /**
     * Devis d'un chantier, du plus récent au plus ancien.
     * GET /api/v1/construction-requests/{constructionRequest}/quotes
     *
     * Lecture ouverte au client propriétaire comme à l'équipe (policy `view`) :
     * le client doit pouvoir relire ce qu'on lui a envoyé.
     *
     * ⚠️ F3.9 — Les **brouillons sont masqués au client**. La règle manquait :
     * la requête renvoyait toute la table, donc un chiffrage encore en cours de
     * composition — montants provisoires, lots incomplets — était lisible par le
     * client avant que l'équipe ne l'ait envoyé. C'est le contraire de ce que
     * disait l'intention (« relire ce qu'on lui a **envoyé** »), et un devis
     * qu'on découvre puis qui change est le meilleur moyen de perdre la
     * confiance qu'on cherche à construire. L'équipe, elle, voit tout : c'est
     * elle qui compose.
     */
    public function index(Request $request, ConstructionRequest $constructionRequest): AnonymousResourceCollection
    {
        Gate::authorize('view', $constructionRequest);

        $quotes = $constructionRequest->quotes()->with('author');

        if (! $request->user()?->can('gerer:chantiers')) {
            $quotes->where('status', '!=', ConstructionQuoteStatus::BROUILLON->value);
        }

        return ConstructionQuoteResource::collection($quotes->get());
    }

    /**
     * Compose un devis (back-office). POST /api/v1/construction-requests/{id}/quotes
     */
    public function compose(
        ComposeConstructionQuoteRequest $request,
        ConstructionRequest $constructionRequest,
        ConstructionQuoteComposer $composer
    ): JsonResponse {
        $data = $request->validated();

        $quote = $composer->composeFor(
            $constructionRequest,
            $data['lines'],
            isset($data['margin_rate']) ? (float) $data['margin_rate'] : null,
            $data['valid_until'] ?? null,
            $request->user()->id,
        );

        // Le dossier passe « en étude » dès qu'un chiffrage existe — sauf s'il est
        // déjà plus avancé : un devis complémentaire sur un chantier en cours ne
        // doit pas faire régresser son statut.
        if ($this->isBeforeStudy($constructionRequest)) {
            $constructionRequest->update(['status' => ConstructionRequestStatus::EN_ETUDE->value]);
        }

        return ApiResponse::created(['quote' => ConstructionQuoteResource::make($quote)]);
    }

    /**
     * Envoie un devis au client (back-office).
     * PATCH /api/v1/construction-quotes/{quote}/send
     */
    public function send(ConstructionQuote $quote): JsonResponse
    {
        // Renvoyer un devis déjà accepté ou refusé n'a pas de sens : la réponse du
        // client serait écrasée en silence.
        if ($quote->status !== ConstructionQuoteStatus::BROUILLON) {
            throw ValidationException::withMessages([
                'status' => ['Seul un devis en brouillon peut être envoyé.'],
            ]);
        }

        $quote->update([
            'status' => ConstructionQuoteStatus::ENVOYE->value,
            'sent_at' => now(),
        ]);

        if ($this->isBeforeStudy($quote->constructionRequest, orAtStudy: true)) {
            $quote->constructionRequest->update([
                'status' => ConstructionRequestStatus::DEVIS_ENVOYE->value,
            ]);
        }

        // F3.9 — Prévenir le CLIENT. L'envoi ne se voyait nulle part : le statut
        // basculait en base et le client devait deviner qu'un chiffrage
        // l'attendait. Le devis pack du team building notifiait déjà l'entreprise
        // depuis B9.3 ; la construction avait été oubliée.
        ConstructionQuoteSent::dispatch($quote->fresh());

        return ApiResponse::success(['quote' => ConstructionQuoteResource::make($quote->fresh())]);
    }

    /**
     * Le client accepte le devis. PATCH /api/v1/construction-quotes/{quote}/accept
     */
    public function accept(ConstructionQuote $quote, QuoteConversionService $conversion): JsonResponse
    {
        Gate::authorize('respond', $quote->constructionRequest);

        $this->assertAnswerable($quote);

        $quote->update([
            'status' => ConstructionQuoteStatus::ACCEPTE->value,
            'accepted_at' => now(),
        ]);
        $quote->constructionRequest->update([
            'status' => ConstructionRequestStatus::ACCEPTEE->value,
        ]);

        // F8.14 — L'ACCEPTATION DEVIENT EXIGIBLE. Comme en team building (et
        // comme les devis génériques avant F8.11), accepter ne faisait que
        // changer deux colonnes `status` : le client validait un chantier à
        // plusieurs millions et rien ne devenait payable. Conversion idempotente.
        $booking = $conversion->convertConstruction($quote);

        // Après la conversion : elle tourne en transaction, et un e-mail parti
        // avant un rollback annoncerait une réservation inexistante.
        $quote->constructionRequest->client?->notify(
            new ConstructionQuoteAcceptedNotification($quote, $booking)
        );

        return ApiResponse::success([
            'quote' => ConstructionQuoteResource::make($quote->fresh()->load('booking')),
            'booking' => BookingResource::make($booking),
        ]);
    }

    /**
     * Le client refuse le devis. PATCH /api/v1/construction-quotes/{quote}/refuse
     *
     * Le dossier reste en `DEVIS_ENVOYE` : un refus n'annule pas la demande, il
     * appelle un devis révisé — et c'est à l'équipe de décider de la suite.
     */
    public function refuse(ConstructionQuote $quote): JsonResponse
    {
        Gate::authorize('respond', $quote->constructionRequest);

        $this->assertAnswerable($quote);

        $quote->update(['status' => ConstructionQuoteStatus::REFUSE->value]);

        return ApiResponse::success(['quote' => ConstructionQuoteResource::make($quote->fresh())]);
    }

    /**
     * Un devis n'est répondable que s'il a été envoyé (et pas déjà tranché).
     */
    private function assertAnswerable(ConstructionQuote $quote): void
    {
        if ($quote->status !== ConstructionQuoteStatus::ENVOYE) {
            throw ValidationException::withMessages([
                'status' => ['Seul un devis envoyé peut être accepté ou refusé.'],
            ]);
        }
    }

    /**
     * Le dossier est-il encore en amont de l'étude ? Sert à ne JAMAIS faire
     * régresser le statut d'un chantier accepté, en cours ou terminé lorsqu'un
     * devis complémentaire est chiffré ou envoyé.
     */
    private function isBeforeStudy(ConstructionRequest $request, bool $orAtStudy = false): bool
    {
        $early = [ConstructionRequestStatus::SOUMISE];

        if ($orAtStudy) {
            $early[] = ConstructionRequestStatus::EN_ETUDE;
        }

        return in_array($request->status, $early, strict: true);
    }
}
