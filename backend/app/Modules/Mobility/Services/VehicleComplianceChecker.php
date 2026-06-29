<?php

namespace App\Modules\Mobility\Services;

use App\Modules\Mobility\Models\Vehicle;

/**
 * Contrôle des champs de conformité obligatoires d'un véhicule (phase B7.3).
 *
 * Bloque la validation tant que les champs requis (selon la catégorie) sont
 * absents :
 *  - PIROGUE : capacité, gilets de sauvetage, conformité météo, conformité prestataire.
 *  - MOTORISÉ : assurance, capacité, et identité du chauffeur si chauffeur inclus.
 */
class VehicleComplianceChecker
{
    /**
     * Liste des champs de conformité manquants (clés lisibles).
     *
     * @return list<string>
     */
    public function missingFields(Vehicle $vehicle): array
    {
        $missing = [];

        // Capacité requise dans tous les cas.
        if (($vehicle->capacity ?? 0) < 1) {
            $missing[] = 'capacity';
        }

        if ($vehicle->type->isMotorized()) {
            // Transport motorisé : assurance (+ identité chauffeur si chauffeur inclus).
            if (blank($vehicle->insurance_ref)) {
                $missing[] = 'insurance_ref';
            }
            if ($vehicle->has_driver && blank($vehicle->driver_identity)) {
                $missing[] = 'driver_identity';
            }
        } else {
            // Pirogue : gilets, météo et conformité prestataire.
            if (($vehicle->life_jackets_count ?? 0) < 1) {
                $missing[] = 'life_jackets_count';
            }
            if ($vehicle->weather_compliant !== true) {
                $missing[] = 'weather_compliant';
            }
            if ($vehicle->provider_compliant !== true) {
                $missing[] = 'provider_compliant';
            }
        }

        return $missing;
    }

    /**
     * Le véhicule satisfait-il tous les contrôles de conformité ?
     */
    public function isCompliant(Vehicle $vehicle): bool
    {
        return $this->missingFields($vehicle) === [];
    }
}
