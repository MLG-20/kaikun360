<?php

namespace App\Listeners;

use App\Events\RequestCreated;
use App\Models\User;
use App\Notifications\NewRequestToHandleNotification;
use Illuminate\Support\Facades\Notification;

/**
 * À la création d'une demande, prévient les agents disponibles (profils
 * back-office : permission `consulter:dashboard-admin`).
 */
class NotifyAvailableAgentsOfRequest
{
    public function handle(RequestCreated $event): void
    {
        $agents = User::permission('consulter:dashboard-admin')->get();

        Notification::send($agents, new NewRequestToHandleNotification($event->request));
    }
}
