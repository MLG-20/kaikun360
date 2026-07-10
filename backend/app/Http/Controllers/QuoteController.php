<?php

namespace App\Http\Controllers;

use App\Enums\QuoteStatus;
use App\Http\Requests\RespondQuoteRequest;
use App\Http\Requests\StoreQuoteRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use App\Models\ServiceRequest;
use App\Support\ApiResponse;
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
            'status' => QuoteStatus::ENVOYE->value,
        ]);

        return ApiResponse::created(['quote' => QuoteResource::make($quote)]);
    }

    /**
     * Consulte un devis. GET /api/v1/quotes/{quote}
     */
    public function show(Quote $quote): JsonResponse
    {
        Gate::authorize('view', $quote);

        return ApiResponse::success(['quote' => QuoteResource::make($quote)]);
    }

    /**
     * Accepte/refuse un devis (demandeur). PATCH /api/v1/quotes/{quote}
     */
    public function respond(RespondQuoteRequest $request, Quote $quote): JsonResponse
    {
        Gate::authorize('respond', $quote);

        // On ne répond qu'à un devis envoyé (ni brouillon, ni déjà tranché).
        if ($quote->status !== QuoteStatus::ENVOYE) {
            throw ValidationException::withMessages([
                'status' => ['Seul un devis envoyé peut être accepté ou refusé.'],
            ]);
        }

        $quote->update(['status' => $request->validated()['status']]);

        return ApiResponse::success(['quote' => QuoteResource::make($quote->fresh())]);
    }
}
