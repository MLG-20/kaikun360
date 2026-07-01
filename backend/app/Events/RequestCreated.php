<?php

namespace App\Events;

use App\Models\ServiceRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis à la création d'une demande client générique (couche transversale, B11.2).
 *
 * Notifie les agents disponibles (listener NotifyAvailableAgentsOfRequest).
 */
class RequestCreated
{
    use Dispatchable;

    public function __construct(public ServiceRequest $request) {}
}
