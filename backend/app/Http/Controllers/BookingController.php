<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Modules\Stay\Models\Stay;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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
            // Charge la chose réservée en une passe (évite les N+1 sur le libellé
            // exposé par BookingResource) ; pour une nuitée, on remonte aussi son
            // bien immobilier, dont le titre sert de libellé.
            ->with(['bookable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                Stay::class => ['property'],
            ])])
            ->latest()
            ->paginate(15);

        return BookingResource::collection($bookings);
    }
}
