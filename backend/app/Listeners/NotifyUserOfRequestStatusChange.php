<?php

namespace App\Listeners;

use App\Events\RequestStatusChanged;
use App\Notifications\RequestStatusChangedNotification;

/**
 * À chaque changement de statut, notifie le demandeur (notification mise en
 * file : push/WhatsApp/email — B16).
 */
class NotifyUserOfRequestStatusChange
{
    public function handle(RequestStatusChanged $event): void
    {
        $event->request->user?->notify(new RequestStatusChangedNotification($event->request));
    }
}
