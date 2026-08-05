<?php

namespace App\Modules\Build\Events;

use App\Modules\Build\Models\ConstructionRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis à la création d'une demande de construction (F8.15.b).
 *
 * Alimente la file de traitement de l'équipe, via le listener
 * `NotifyAdminsOfConstructionRequest` — comme `TeamBuildingRequestCreated` le
 * fait pour les demandes d'entreprise.
 *
 * ⚠️ Il n'existait pas : `POST /construction-requests` créait le dossier, semait
 * ses jalons, calculait son estimation… et **ne prévenait personne**. Tant que
 * l'écran public déposait une demande générique (qui, elle, alerte l'équipe
 * depuis B11.2), le manque restait invisible.
 */
class ConstructionRequestCreated
{
    use Dispatchable;

    public function __construct(public ConstructionRequest $request) {}
}
