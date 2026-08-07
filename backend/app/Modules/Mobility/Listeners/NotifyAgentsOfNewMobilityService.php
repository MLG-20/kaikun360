<?php

namespace App\Modules\Mobility\Listeners;

use App\Models\User;
use App\Modules\Mobility\Events\MobilityServiceCreated;
use App\Modules\Mobility\Notifications\NewMobilityServiceToValidateNotification;
use Illuminate\Support\Facades\Notification;

/**
 * À la programmation d'un départ, prévient tous les utilisateurs habilités à
 * valider la mobilité (permission `valider:vehicule` : agents, admins, super_admin).
 *
 * ⚠️ **Aucune permission neuve** : `valider:vehicule` couvre déjà la mobilité
 * dans son ensemble. En créer une seconde obligerait à ré-attribuer des droits
 * à toute l'équipe pour un geste que les mêmes personnes font déjà.
 */
class NotifyAgentsOfNewMobilityService
{
    public function handle(MobilityServiceCreated $event): void
    {
        $agents = User::permission('valider:vehicule')->get();

        Notification::send($agents, new NewMobilityServiceToValidateNotification($event->mobilityService));
    }
}
