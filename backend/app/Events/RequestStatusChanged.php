<?php

namespace App\Events;

use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis lors d'un changement de statut d'une demande (couche transversale, B11.2).
 *
 * Déclenche la notification du demandeur (listener NotifyUserOfRequestStatusChange),
 * qui s'appuie sur une notification mise en file (push/WhatsApp/email — B16).
 */
class RequestStatusChanged
{
    use Dispatchable;

    public function __construct(
        public ServiceRequest $request,
        public RequestStatus $from,
        public RequestStatus $to,
    ) {}
}
