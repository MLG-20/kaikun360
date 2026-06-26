<?php

namespace App\Modules\Immo\Listeners;

use App\Models\User;
use App\Modules\Immo\Events\PropertyCreated;
use App\Modules\Immo\Notifications\NewPropertyToValidateNotification;
use Illuminate\Support\Facades\Notification;

/**
 * À la création d'un bien, prévient tous les utilisateurs habilités à valider
 * (permission `valider:bien` : agents, admins, super_admin).
 */
class NotifyAgentsOfNewProperty
{
    public function handle(PropertyCreated $event): void
    {
        // Le scope `permission()` de Spatie retourne les users ayant la permission
        // (directement ou via leur rôle).
        $agents = User::permission('valider:bien')->get();

        Notification::send($agents, new NewPropertyToValidateNotification($event->property));
    }
}
