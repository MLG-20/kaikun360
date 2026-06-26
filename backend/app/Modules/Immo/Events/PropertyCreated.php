<?php

namespace App\Modules\Immo\Events;

use App\Modules\Immo\Models\Property;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis à la création d'un bien par un propriétaire.
 *
 * Déclenche la mise en file de validation (notification des agents), via le
 * listener NotifyAgentsOfNewProperty.
 */
class PropertyCreated
{
    use Dispatchable;

    public function __construct(public Property $property) {}
}
