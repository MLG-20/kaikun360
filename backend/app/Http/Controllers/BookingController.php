<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
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
            ->latest()
            ->paginate(15);

        return BookingResource::collection($bookings);
    }
}
