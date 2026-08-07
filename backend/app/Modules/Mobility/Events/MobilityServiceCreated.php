<?php

namespace App\Modules\Mobility\Events;

use App\Modules\Mobility\Models\MobilityService;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis à la programmation d'un départ par un prestataire (F8.23).
 *
 * Déclenche la mise en file de validation (notification des agents), via le
 * listener NotifyAgentsOfNewMobilityService — exactement comme `VehicleCreated`.
 */
class MobilityServiceCreated
{
    use Dispatchable;

    public function __construct(public MobilityService $mobilityService) {}
}
