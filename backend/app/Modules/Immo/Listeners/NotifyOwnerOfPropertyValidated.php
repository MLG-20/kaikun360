<?php

namespace App\Modules\Immo\Listeners;

use App\Modules\Immo\Events\PropertyValidated;
use App\Modules\Immo\Notifications\PropertyValidatedNotification;

/**
 * À la validation d'un bien, informe son propriétaire de la publication.
 */
class NotifyOwnerOfPropertyValidated
{
    public function handle(PropertyValidated $event): void
    {
        $event->property->owner?->notify(
            new PropertyValidatedNotification($event->property)
        );
    }
}
