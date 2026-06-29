<?php

namespace App\Modules\Mobility\Listeners;

use App\Modules\Mobility\Events\VehicleValidated;
use App\Modules\Mobility\Notifications\VehicleValidatedNotification;

/**
 * À la validation d'un véhicule, prévient son prestataire qu'il est en ligne.
 */
class NotifyProviderOfVehicleValidated
{
    public function handle(VehicleValidated $event): void
    {
        $event->vehicle->provider?->notify(new VehicleValidatedNotification($event->vehicle));
    }
}
