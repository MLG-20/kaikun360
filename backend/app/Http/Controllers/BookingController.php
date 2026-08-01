<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Modules\Stay\Models\Stay;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Support\ApiResponse;

/**
 * Réservations de l'utilisateur — couche transversale (phase B11.3).
 */
class BookingController extends Controller
{
    /**
     * Mes réservations (tous univers confondus). GET /api/v1/bookings/my
     */
    public function my(Request $request): AnonymousResourceCollection
    {
        $bookings = Booking::where('user_id', $request->user()->id)
            // Charge la chose réservée en une passe (évite les N+1 sur le libellé
            // exposé par BookingResource) ; pour une nuitée, on remonte aussi son
            // bien immobilier, dont le titre sert de libellé.
            ->with(['bookable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                Stay::class => ['property'],
            ])])
            // F8.6 — l'état de règlement exposé par la ressource lit les
            // paiements : sans ce chargement, 15 réservations = 15 requêtes.
            ->with('payments')
            ->latest()
            ->paginate(15);

        return BookingResource::collection($bookings);
    }

    /**
     * Détail d'une de MES réservations. GET /api/v1/bookings/{booking}
     *
     * Réservé au titulaire de la réservation : une réservation qui n'appartient
     * pas à l'utilisateur connecté renvoie 403. Charge la chose réservée (comme
     * `my`) pour exposer le libellé de l'élément. Alimente l'écran « détail
     * d'une réservation » de l'espace client (F3.4).
     */
    public function show(Request $request, Booking $booking): JsonResponse
    {
        abort_unless($booking->user_id === $request->user()->id, 403);

        // Charge le bookable (et le bien d'une nuitée) pour le libellé lisible.
        $booking->load(['bookable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
            Stay::class => ['property'],
        ]), 'payments']);

        return ApiResponse::success(['booking' => BookingResource::make($booking)]);
    }
}
