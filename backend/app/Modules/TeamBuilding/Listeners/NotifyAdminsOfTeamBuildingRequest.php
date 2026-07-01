<?php

namespace App\Modules\TeamBuilding\Listeners;

use App\Models\User;
use App\Modules\TeamBuilding\Events\TeamBuildingRequestCreated;
use App\Modules\TeamBuilding\Notifications\NewTeamBuildingRequestNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Alimente la file d'attente admin dédiée : prévient les profils back-office
 * (permission `consulter:dashboard-admin`) d'une nouvelle demande.
 */
class NotifyAdminsOfTeamBuildingRequest
{
    public function handle(TeamBuildingRequestCreated $event): void
    {
        $admins = User::permission('consulter:dashboard-admin')->get();

        Notification::send($admins, new NewTeamBuildingRequestNotification($event->request));
    }
}
