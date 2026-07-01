<?php

namespace App\Modules\TeamBuilding\Events;

use App\Modules\TeamBuilding\Models\TeamBuildingRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis à la création d'une demande de team building.
 *
 * Alimente la file d'attente admin dédiée (notification), via le listener
 * NotifyAdminsOfTeamBuildingRequest.
 */
class TeamBuildingRequestCreated
{
    use Dispatchable;

    public function __construct(public TeamBuildingRequest $request) {}
}
