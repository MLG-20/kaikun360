<?php

namespace App\Modules\Mobility\Events;

use App\Modules\Mobility\Models\Vehicle;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis lorsqu'un agent valide (publie) un véhicule.
 *
 * Le véhicule apparaît alors dans la recherche ; le prestataire est notifié via
 * le listener NotifyProviderOfVehicleValidated.
 */
class VehicleValidated
{
    use Dispatchable;

    public function __construct(public Vehicle $vehicle) {}
}
