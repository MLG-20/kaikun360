<?php

namespace App\Http\Controllers;

use App\Enums\WaitlistEntryStatus;
use App\Http\Requests\StoreWaitlistEntryRequest;
use App\Http\Resources\WaitlistEntryResource;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Notifications\NewWaitlistEntryNotification;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

/**
 * Liste d'attente avant ouverture officielle (2026-08-14).
 *
 * Dépôt PUBLIC, comme la page Contact (F2.8.1). Pas encore d'écran de
 * consultation back-office — reporté, voir le plan de mise en place.
 */
class WaitlistController extends Controller
{
    /**
     * Enregistre une inscription. POST /api/v1/waitlist (public).
     */
    public function store(StoreWaitlistEntryRequest $request): JsonResponse
    {
        $entry = WaitlistEntry::create($request->validated() + [
            'status' => WaitlistEntryStatus::NOUVEAU->value,
        ]);

        Notification::send(
            User::permission('consulter:dashboard-admin')->get(),
            new NewWaitlistEntryNotification($entry),
        );

        return ApiResponse::created(['waitlist_entry' => WaitlistEntryResource::make($entry)]);
    }
}
