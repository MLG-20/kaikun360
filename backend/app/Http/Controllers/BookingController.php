<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Quote;
use App\Modules\Build\Models\ConstructionQuote;
use App\Modules\Stay\Models\Stay;
use App\Modules\TeamBuilding\Models\TeamBuildingQuote;
use App\Support\ApiResponse;
use App\Support\Trash\PersonalHiding;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
            // F11.5 — les réservations rangées par le client quittent SA liste.
            // ⚠️ Filtre d'AFFICHAGE seulement : la ligne reste entière en base.
            // Le back-office, la comptabilité et les reversements au partenaire
            // continuent de la voir — une réservation est un contrat, pas un
            // objet dont le client dispose seul.
            ->whereNull('hidden_at')
            // Charge la chose réservée en une passe (évite les N+1 sur le libellé
            // exposé par BookingResource) ; pour une nuitée, on remonte aussi son
            // bien immobilier, dont le titre sert de libellé.
            ->with(['bookable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                Stay::class => ['property'],
                // F8.14 — un devis team building tire son libellé de sa demande
                // (ville, participants) : sans ce chargement, une requête par ligne.
                TeamBuildingQuote::class => ['request'],
                ConstructionQuote::class => ['constructionRequest'],
                Quote::class => ['request'],
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
            TeamBuildingQuote::class => ['request'],
            ConstructionQuote::class => ['constructionRequest'],
            Quote::class => ['request'],
        ]), 'payments']);

        return ApiResponse::success(['booking' => BookingResource::make($booking)]);
    }

    /**
     * Range une de MES réservations dans ma corbeille. POST /api/v1/bookings/{booking}/hide
     *
     * ⚠️ **Ne supprime rien, et ne peut pas le faire** : `Booking` n'est pas en
     * `SoftDeletes` et ne le sera pas. Seule la colonne `hidden_at` est écrite,
     * honorée par la seule liste du client. Le geste vit ici plutôt que dans
     * `TrashController` pour la même raison qu'en F11.4 : ranger appartient à
     * l'écran d'origine, qui sait refuser et dire pourquoi.
     */
    public function hide(Request $request, Booking $booking, PersonalHiding $corbeille): JsonResponse
    {
        abort_unless($booking->user_id === $request->user()->id, 403);

        // ⚠️ Une réservation à venir ne se range pas : elle s'annule. La cacher
        // n'allègerait pas la liste, ça effacerait un rendez-vous des yeux de la
        // seule personne qui doit s'y présenter.
        if ($raison = $corbeille->raisonDeBlocage($booking, $request->user())) {
            return ApiResponse::error($raison, 422);
        }

        $corbeille->masquer($booking, $request->user());

        return ApiResponse::success([
            'message' => 'Réservation rangée dans votre corbeille. Elle y reste disponible : rien n’est supprimé.',
        ]);
    }
}
