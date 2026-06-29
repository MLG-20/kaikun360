<?php

namespace App\Modules\Mobility\Listeners;

use App\Models\User;
use App\Modules\Mobility\Events\VehicleCreated;
use App\Modules\Mobility\Notifications\NewVehicleToValidateNotification;
use Illuminate\Support\Facades\Notification;

/**
 * À la création d'un véhicule, prévient tous les utilisateurs habilités à valider
 * (permission `valider:vehicule` : agents, admins, super_admin).
 */
class NotifyAgentsOfNewVehicle
{
    public function handle(VehicleCreated $event): void
    {
        $agents = User::permission('valider:vehicule')->get();

        Notification::send($agents, new NewVehicleToValidateNotification($event->vehicle));
    }
}
