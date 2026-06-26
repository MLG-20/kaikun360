<?php

namespace App\Modules\Immo\Events;

use App\Modules\Immo\Models\Property;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis lorsqu'un agent valide (publie) un bien.
 *
 * Déclenche la notification du propriétaire (« votre bien est en ligne »),
 * via le listener NotifyOwnerOfPropertyValidated.
 */
class PropertyValidated
{
    use Dispatchable;

    public function __construct(public Property $property) {}
}
