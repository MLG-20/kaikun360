<?php

namespace App\Modules\Mobility\Events;

use App\Modules\Mobility\Models\Vehicle;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis à la création d'un véhicule par un prestataire.
 *
 * Déclenche la mise en file de validation (notification des agents), via le
 * listener NotifyAgentsOfNewVehicle.
 */
class VehicleCreated
{
    use Dispatchable;

    public function __construct(public Vehicle $vehicle) {}
}
