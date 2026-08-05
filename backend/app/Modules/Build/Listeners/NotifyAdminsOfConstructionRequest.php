<?php

namespace App\Modules\Build\Listeners;

use App\Models\User;
use App\Modules\Build\Events\ConstructionRequestCreated;
use App\Modules\Build\Notifications\NewConstructionRequestNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Prévient les profils back-office (`consulter:dashboard-admin`) qu'un chantier
 * attend un chiffrage — même vivier que les demandes génériques et team building.
 */
class NotifyAdminsOfConstructionRequest
{
    public function handle(ConstructionRequestCreated $event): void
    {
        $admins = User::permission('consulter:dashboard-admin')->get();

        Notification::send($admins, new NewConstructionRequestNotification($event->request));
    }
}
